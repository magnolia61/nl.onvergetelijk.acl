<?php

/**
 * =======================================================================================
 * FUNCTIE-INDEX: acl.helpers.php
 * =======================================================================================
 *   acl_group_get()  HELPER: GET GROUP STATUS (MET CACHE)
 * =======================================================================================
 */

/**
 * ============================================================================
 * HELPER: GET GROUP STATUS (MET CACHE)
 * ============================================================================
 * Haalt efficiënt groepslidmaatschappen op.
 * Gebruikt een statische cache om te voorkomen dat we voor elke check de DB belasten.
 * LET OP: De cache wordt niet invalid bij writes! Daarom gebruiken write functies (add/rem) een live check.
 */
function acl_group_get($contactid, $group_id, $group_label, $reset = FALSE) {

    $extdebug = 'acl.helpers'; // Kanaal voor centrale debug-config; niveau wordt opgezocht in ozk.debug.config.php

    // 1. Statische cache: Dit blijft in het geheugen zolang het script draait (per contact)
    static $contact_groups_cache = [];

    // Na een write moet de cache voor dit contact worden geleegd zodat reads daarna actueel zijn.
    if ($reset) {
        unset($contact_groups_cache[$contactid]);
    }

    // 2. Als we de groepen voor dit contact nog niet hebben, haal ze ALLEMAAL in 1x op
    if (!isset($contact_groups_cache[$contactid])) {

        wachthond($extdebug,3, "########################################################################");
        wachthond($extdebug,3, "### ACL - GET INFO ABOUT CID $contactid (ALL GROUPS)");
        wachthond($extdebug,3, "########################################################################");

        $contact_groups_cache[$contactid] = [];

        // Gebruik API4 voor snelheid
        $results = civicrm_api4('GroupContact', 'get', [
            'checkPermissions' => FALSE,
            'select' => ['group_id', 'status', 'group_id:label'],
            'where' => [['contact_id', '=', $contactid]]
        ]);

        // Stop resultaten in de cache met group_id als sleutel
        foreach ($results as $row) {
            $gid = $row['group_id'];
            $contact_groups_cache[$contactid][$gid] = [
                'status' => $row['status'],
                'label'  => $row['group_id:label'] ?? ''
            ];
        }
    }

    // 3. Nu doen we de check puur in het geheugen (Razendsnel!)
    $group_data     = $contact_groups_cache[$contactid][$group_id] ?? NULL;
    $group_count    = 0;
    $group_member   = 0;
    $group_status   = NULL;
    $fetched_label  = $group_label;

    if ($group_data) {
        // We hebben een record gevonden (Added, Removed of Pending)
        $group_count    = 1;
        $group_status   = $group_data['status'];
        $fetched_label  = $group_data['label'];

        if ($group_status == 'Added') {
            $group_member = 1;
        }
    }

    // 4. Return array
    return array(
        'contact_id'   => $contactid,
        'group_status' => $group_status,
        'group_label'  => $fetched_label,
        'group_count'  => $group_count,
        'group_member' => $group_member,
    );
}
