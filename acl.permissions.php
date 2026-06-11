<?php

/**
 * ============================================================================
 * ACL LOGIC: PERMISSIES (GROEPEN & DRUPAL ROLLEN)
 * ============================================================================
 * Functies voor het beheren van CiviCRM groepslidmaatschappen en Drupal rollen.
 *
 * @functions
 *   permissions_add()       Voeg contact toe aan CiviCRM groep + Drupal rol
 *   permissions_rem()       Verwijder contact uit CiviCRM groep + Drupal rol
 *   acl_group_remove()      Verwijder contact uit één of meerdere groepen (historie behoud)
 *   acl_group_update()      Alias voor permissions_add (backward compatibility)
 *   acl_group_create()      Alias voor permissions_add (backward compatibility)
 *   cms_rol_check()         Controleer of Drupal gebruiker een rol heeft
 *   cms_rol_add()           Voeg Drupal rol toe aan gebruiker
 *   cms_rol_remove()        Verwijder Drupal rol van gebruiker
 * ============================================================================
 */

/**
 * ============================================================================
 * HELPER: PERMISSIONS ADD (ROBUUSTE VERSIE)
 * ============================================================================
 * Voegt toe. Voorkomt "Duplicate Entry" fouten door te checken of er al een
 * (verwijderde) regel bestaat. Zo ja -> Update. Zo nee -> Create.
 * Gebruikt de bulk-cache van acl_group_get() als eerste check zodat we de DB
 * alleen raken als er daadwerkelijk iets te doen is.
 */
function permissions_add($contact_id, $drupal_id, $displayname, $acl_array) {

    $extdebug        = 'acl.custom'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php

    // 1. Variabelen uitpakken
    $aclgroup        = $acl_array['aclgroup']        ?? NULL;
    $cmsrol          = $acl_array['cmsrol']          ?? NULL;
    $acl_group_label = $acl_array['acl_group_label'] ?? NULL;
    $cms_hasrol      = $acl_array['cms_hasrol']      ?? NULL;

    if (empty($contact_id) || empty($aclgroup)) {
        return;
    }

    watchdog('civicrm_timing', base_microtimer("START permissions_add [CID: $contact_id / GRP: $aclgroup]"), NULL, WATCHDOG_DEBUG);

    // -------------------------------------------------------------------------
    // STAP 1: CACHE-CHECK (Goedkoop — geen DB als al lid)
    // -------------------------------------------------------------------------
    $cached = acl_group_get($contact_id, $aclgroup, $acl_group_label);

    if ($cached['group_member'] !== 1) {

        // -------------------------------------------------------------------------
        // STAP 2: DB-CHECK (Alleen als niet al lid — id nodig voor eventuele update)
        // -------------------------------------------------------------------------
        $check = civicrm_api4('GroupContact', 'get', [
            'checkPermissions' => FALSE,
            'select'           => ['id', 'status'],
            'where'            => [
                ['contact_id', '=', $contact_id],
                ['group_id',   '=', $aclgroup],
            ],
            'limit' => 1,
        ])->first();

        $real_status = $check['status'] ?? 'None';
        $link_id     = $check['id']     ?? NULL;

        // Scenario B: Bestaat wel (Removed/Pending), dus UPDATE.
        if ($link_id) {

            wachthond($extdebug, 1, "### ACL - UPDATE ACTIE: $displayname -> Groep $acl_group_label ($aclgroup)", "[Was: $real_status, ID: $link_id]");

            $result = civicrm_api4('GroupContact', 'update', [
                'checkPermissions' => FALSE,
                'where'            => [['id', '=', $link_id]],
                'values'           => ['status' => 'Added'],
            ]);
            wachthond($extdebug, 9, 'API Result GroupContact Update', $result);

        // Scenario C: Bestaat niet, dus CREATE.
        } else {

            wachthond($extdebug, 1, "### ACL - CREATE ACTIE: $displayname -> Groep $acl_group_label ($aclgroup)", "[Nieuw]");

            $result = civicrm_api4('GroupContact', 'create', [
                'checkPermissions' => FALSE,
                'values'           => [
                    'contact_id' => $contact_id,
                    'group_id'   => $aclgroup,
                    'status'     => 'Added',
                ],
            ]);
            wachthond($extdebug, 9, 'API Result GroupContact Create', $result);
        }

        // Cache leegmaken zodat volgende lees actueel is.
        acl_group_get($contact_id, $aclgroup, $acl_group_label, TRUE);
    }

    // -------------------------------------------------------------------------
    // STAP 3: DRUPAL ROL (CMS)
    // -------------------------------------------------------------------------

    if ($drupal_id > 0 && !empty($cmsrol)) {
        if ($cms_hasrol != 1) {
            cms_rol_add($drupal_id, $displayname, $cmsrol);
        }
    }

    watchdog('civicrm_timing', base_microtimer("EINDE permissions_add"), NULL, WATCHDOG_DEBUG);
}

/**
 * ============================================================================
 * HELPER: PERMISSIONS REMOVE (MET HISTORIE BEHOUD)
 * ============================================================================
 */
function permissions_rem($contact_id, $drupal_id, $displayname, $acl_array) {

    $extdebug        = 'acl.custom'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php

    $aclgroup        = $acl_array['aclgroup']        ?? NULL;
    $cmsrol          = $acl_array['cmsrol']          ?? NULL;
    $acl_group_label = $acl_array['acl_group_label'] ?? NULL;

    if (empty($contact_id) || empty($aclgroup)) {
        return;
    }

    watchdog('civicrm_timing', base_microtimer("START permissions_rem [CID: $contact_id / GRP: $aclgroup]"), NULL, WATCHDOG_DEBUG);

    // STAP 1: ID en Status ophalen
    $check = civicrm_api4('GroupContact', 'get', [
        'checkPermissions' => FALSE,
        'select'           => ['id', 'status'],
        'where'            => [
            ['contact_id', '=', $contact_id],
            ['group_id',   '=', $aclgroup],
        ],
        'limit' => 1,
    ])->first();

    $real_status = $check['status'] ?? 'None';
    $link_id     = $check['id']     ?? NULL;

    // STAP 2: Alleen actie ondernemen als ze nu lid ('Added') of hangend ('Pending') zijn.
    if ($link_id && in_array($real_status, ['Added', 'Pending'])) {

        wachthond($extdebug, 1, "### ACL - REMOVE ACTIE: $displayname uit Groep $acl_group_label ($aclgroup)", "[Was: $real_status]");

        // We gebruiken UPDATE naar 'Removed' i.p.v. DELETE.
        // Hierdoor blijft hij zichtbaar in de historie ('Verwijderde groepen').
        $result = civicrm_api4('GroupContact', 'update', [
            'checkPermissions' => FALSE,
            'where'            => [['id', '=', $link_id]],
            'values'           => ['status' => 'Removed'],
        ]);

        wachthond($extdebug, 9, 'API Result GroupContact Set Removed', $result);

        // Cache leegmaken zodat volgende lees actueel is.
        acl_group_get($contact_id, $aclgroup, $acl_group_label, TRUE);
    }

    // STAP 3: DRUPAL ROL
    if ($drupal_id > 0 && !empty($cmsrol)) {
        if (cms_rol_check($drupal_id, $displayname, $cmsrol) == 1) {
            cms_rol_remove($drupal_id, $displayname, $cmsrol);
        }
    }

    watchdog('civicrm_timing', base_microtimer("EINDE permissions_rem"), NULL, WATCHDOG_DEBUG);
}

/**
 * ============================================================================
 * HELPER: GROUP REMOVE (MET HISTORIE BEHOUD)
 * ============================================================================
 */
function acl_group_remove($contactid, $group, $group_label) {

    $extdebug   = 'acl.groups'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php

    $contact_id = $contactid;
    $group_ids  = is_array($group) ? $group : [$group];

    wachthond($extdebug, 4, "contact_id",    $contact_id);
    wachthond($extdebug, 4, "group_array",   $group_ids);

    if ($contact_id AND !empty($group_ids)) {

        // STAP 1: CHECK (Welke van deze groepen zijn NU actief 'Added'?)
        $results = civicrm_api4('GroupContact', 'get', [
            'checkPermissions' => FALSE,
            'select'           => ['id', 'group_id'],
            'where'            => [
                ['contact_id', '=', $contact_id],
                ['group_id',   'IN', $group_ids],
                ['status',     '=', 'Added'],
            ],
        ]);

        $ids_to_remove = [];
        $groups_found  = [];

        foreach ($results as $row) {
            $ids_to_remove[] = $row['id'];
            $groups_found[]  = $row['group_id'];
        }

        // STAP 2: UPDATE NAAR REMOVED
        if (!empty($ids_to_remove)) {
            wachthond($extdebug, 3, "########################################################################");
            wachthond($extdebug, 3, "### ACL - VERWIJDER CID $contact_id UIT GROEPEN $group_label",  $groups_found);
            wachthond($extdebug, 3, "########################################################################");

            civicrm_api4('GroupContact', 'update', [
                'checkPermissions' => FALSE,
                'where'            => [['id', 'IN', $ids_to_remove]],
                'values'           => ['status' => 'Removed'],
            ]);

            wachthond($extdebug, 1, 'Verwijderd uit ACL groepen (Status Removed)', implode(',', $groups_found));

            // Cache leegmaken zodat volgende lees actueel is.
            acl_group_get($contact_id, $groups_found[0], $group_label, TRUE);

        } else {
            wachthond($extdebug, 4, 'Verwijderd uit ACL groep [SKIPPED - WAS GEEN LID]', $group_label);
        }
    }
}

// acl_group_update en acl_group_create zijn feitelijk aliases voor permissions_add
// maar worden behouden voor backward compatibility als ze elders worden aangeroepen.
function acl_group_update($contactid, $group_id, $group_label) {
    permissions_add($contactid, NULL, "CID $contactid", ['aclgroup' => $group_id, 'acl_group_label' => $group_label]);
}

function acl_group_create($contactid, $group_id, $group_label) {
    permissions_add($contactid, NULL, "CID $contactid", ['aclgroup' => $group_id, 'acl_group_label' => $group_label]);
}

/**
 * ============================================================================
 * HELPER: CMS ROL CHECK / ADD / REMOVE (DRUPAL ROLLEN)
 * ============================================================================
 */
function cms_rol_check($drupal_id, $displayname, $cmsrol) {

    $extdebug = 'acl.groups'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php
    $userhasrole = 0;

    // Cache voor Role ID's (Naam -> ID mapping) om Drupal calls te minimaliseren
    static $role_map_cache = [];

    if (empty($drupal_id) OR empty($cmsrol)) {
        return 0;
    }

    $cmsuser = user_load($drupal_id);
    if (!$cmsuser) return 0;

    // Haal Role ID op (uit cache of DB)
    if (!isset($role_map_cache[$cmsrol])) {
        $role = user_role_load_by_name($cmsrol);
        if ($role) {
            $role_map_cache[$cmsrol] = $role->rid;
        } else {
            $role_map_cache[$cmsrol] = FALSE;
        }
    }

    $rid = $role_map_cache[$cmsrol];

    if ($rid && isset($cmsuser->roles[$rid])) {
        $userhasrole = 1;
    }

    return $userhasrole;
}

function cms_rol_add($drupal_id, $displayname, $cmsrol) {

    $extdebug = 'acl.groups'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php

    wachthond($extdebug, 1, 'drupal_id',     $drupal_id);
    wachthond($extdebug, 1, 'displayname',   $displayname);

    if (!$drupal_id OR !$cmsrol) {
        wachthond($extdebug, 1, 'cms_rol_add', 'input parameters missing');
        return;
    }

    // Double check: heeft hij de rol al?
    if (cms_rol_check($drupal_id, $displayname, $cmsrol) == 1) {
        return; // Niets doen
    }

    watchdog('civicrm_timing', base_microtimer("START cms_rol_add [UID: $drupal_id / ROL: $cmsrol]"), NULL, WATCHDOG_DEBUG);

    wachthond($extdebug, 3, "########################################################################");
    wachthond($extdebug, 3, "### ACL - TOEVOEGEN ROL $cmsrol AAN UID $drupal_id",          $displayname);
    wachthond($extdebug, 3, "########################################################################");

    if ($role = user_role_load_by_name($cmsrol)) {
        user_multiple_role_edit(array($drupal_id), 'add_role', $role->rid);
    }

    watchdog('civicrm_timing', base_microtimer("EINDE cms_rol_add"), NULL, WATCHDOG_DEBUG);
}

function cms_rol_remove($drupal_id, $displayname, $cmsrol) {

    $extdebug = 'acl.groups'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php

    wachthond($extdebug, 1, 'drupal_id',     $drupal_id);
    wachthond($extdebug, 1, 'displayname',   $displayname);

    if (!$drupal_id OR !$cmsrol) {
        wachthond($extdebug, 1, 'cms_rol_remove', 'input parameters missing');
        return;
    }

    // Double check: heeft hij de rol wel? Zo niet, dan hoeven we niks te doen.
    if (cms_rol_check($drupal_id, $displayname, $cmsrol) == 0) {
        return;
    }

    watchdog('civicrm_timing', base_microtimer("START cms_rol_remove [UID: $drupal_id / ROL: $cmsrol]"), NULL, WATCHDOG_DEBUG);

    wachthond($extdebug, 3, "########################################################################");
    wachthond($extdebug, 3, "### ACL - VERWIJDEREN ROL $cmsrol VAN UID $drupal_id",        $displayname);
    wachthond($extdebug, 3, "########################################################################");

    if ($role = user_role_load_by_name($cmsrol)) {
        user_multiple_role_edit(array($drupal_id), 'remove_role', $role->rid);
    }

    watchdog('civicrm_timing', base_microtimer("EINDE cms_rol_remove"), NULL, WATCHDOG_DEBUG);
}
