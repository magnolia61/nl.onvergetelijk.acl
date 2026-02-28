<?php

require_once 'acl.civix.php';

use CRM_Acl_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function acl_civicrm_config(&$config): void {
  _acl_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function acl_civicrm_install(): void {
  _acl_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function acl_civicrm_enable(): void {
  _acl_civix_civicrm_enable();
}

/*
function acl_civicrm_custom($op, $groupID, $entityID) {
    return;
}
*/

/**
 * ============================================================================
 * HELPER: SMART SYNC
 * ============================================================================
 * Deze functie is het hart van de opschoning.
 * Doel: Zorg dat de gebruiker ALLEEN in de $target_group_id zit,
 * en verwijderd wordt uit alle andere groepen in $all_possible_groups.
 *
 * Voorkomt het "Jojo-effect" (verwijderen/toevoegen) door eerst de status te checken.
 */
function acl_group_sync($contact_id, $target_group_id, $all_possible_groups, $label_prefix) {
    
    $extdebug = 0; // Logging niveau voor sync details

    // Loop door ALLE mogelijke opties (bijv. alle kampen: kk1, kk2, top, etc.)
    foreach ($all_possible_groups as $group_id) {
        
        // 1. Check HUIDIGE status (Lezen is goedkoop via de statische cache)
        $status_info = acl_group_get($contact_id, $group_id, $label_prefix);
        $is_member_now = $status_info['group_member']; 

        // 2. Bepaal de ACTIE (Vergelijken Huidig vs Gewenst)
        if ($group_id == $target_group_id) {
            // SCENARIO A: Dit is de DOELGROEP.
            // Actie: Moet lid zijn. Als hij dat niet is -> Toevoegen.
            if ($is_member_now == 0) {
                wachthond($extdebug, 2, "SYNC [$label_prefix]: Huidig=NOT -> Actie=Toevoegen", "Groep $group_id");
                // Gebruik create (of permissions_add) die intern nog een dubbelcheck doet
                acl_group_create($contact_id, $group_id, $label_prefix);
            }
        } else {
            // SCENARIO B: Dit is een 'FOUTE' groep (een ander kamp/rol).
            // Actie: Moet GEEN lid zijn. Als hij dat wel is -> Verwijderen.
            if ($is_member_now == 1) {
                wachthond($extdebug, 2, "SYNC [$label_prefix]: Huidig=YES -> Actie=Verwijderen", "Groep $group_id");
                // Gebruik remove die intern nog een dubbelcheck doet
                acl_group_remove($contact_id, $group_id, $label_prefix);
            }
        }
    }
}

/**
 * ============================================================================
 * HOOFDFUNCTIE: ACL CONFIGURATIE
 * ============================================================================
 * Wordt aangeroepen door de taak (met arrays) OF door trigger (met alleen ID).
 */
function acl_civicrm_configure($contact_id, $array_contditjaar = NULL, $ditjaar_array = NULL, $allpart_array = NULL, $drupal_id = NULL, $eventrollen_array = NULL) {

    $extdebug       = 0; 
    $apidebug       = FALSE;
    $extwrite       = 1; 
    $regpast        = 1;
    $today_datetime = date("Y-m-d H:i:s");

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 1.X CONFIGUREER ACL, GROEPEN & PERMISSIES");
    wachthond($extdebug,2, "########################################################################");

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 1.0 INPUT NORMALISATIE & DATA OPHALEN");
    wachthond($extdebug,2, "########################################################################");

    // -------------------------------------------------------------------------
    // 0. INPUT NORMALISATIE & DATA OPHALEN
    // -------------------------------------------------------------------------

    // Veiligheidscheck: Hebben we een bruikbaar ID?
    if (!is_numeric($contact_id) || $contact_id <= 0) {
        wachthond($extdebug, 1, "ACL ABORT: Geen geldig Contact ID ($contact_id)");
        return;
    }

    // STAP 1: CONTACT DATA (Basisgegevens uit CiviCRM)
    if (empty($array_contditjaar)) {
        if (function_exists('base_cid2cont')) {
            $array_contditjaar = base_cid2cont($contact_id);
            if (empty($array_contditjaar)) {
                wachthond($extdebug, 1, "ACL ERROR: base_cid2cont gaf geen resultaat voor CID: $contact_id. Bestaat het contact nog?");
            } else {
                wachthond($extdebug, 3, ">> CONTACT DATA OPHALEN voor CID: $contact_id", "OK");
            }
        } else {
            wachthond($extdebug, 1, "ACL ABORT: Functie base_cid2cont ontbreekt. Kan proces niet voortzetten.");
            return; 
        }
    } else {
        wachthond($extdebug, 3, ">> CONTACT DATA: Was al gevuld voor CID: $contact_id");
    }

    // STAP 2: ALLPART (Alle inschrijvingen voor dit jaar/periode)
    if (empty($allpart_array)) {
        if (function_exists('base_find_allpart')) {
            $allpart_array = base_find_allpart($contact_id, $today_datetime);
            if (empty($allpart_array)) {
                wachthond($extdebug, 2, "ACL INFO: base_find_allpart is leeg voor CID $contact_id. Geen inschrijvingen gevonden voor deze periode.");
            } else {
                wachthond($extdebug, 3, ">> ALLPART OPHALEN: " . count($allpart_array) . " elementen gevonden.");
            }
        } else {
            wachthond($extdebug, 1, "ACL WAARSCHUWING: Functie base_find_allpart ontbreekt.");
        }
    } else {
        wachthond($extdebug, 3, ">> ALLPART DATA: Was al gevuld.");
    }

    // STAP 3: PID BEPALEN (Deelnemer ID)
    $calculated_pid = NULL;
    if (!empty($allpart_array)) {
        $calculated_pid = $allpart_array['result_allpart_pos_part_id'] 
            ?? $allpart_array['result_allpart_pen_part_id']
            ?? $allpart_array['result_allpart_wait_part_id']
            ?? $allpart_array['result_allpart_one_part_id']
            ?? NULL;
        
        if (!$calculated_pid) {
            wachthond($extdebug, 2, "ACL PID: allpart_array gevuld, maar geen PID met status Positive, Pending of Waiting gevonden.");
        } else {
            wachthond($extdebug, 3, ">> PID BEPAALD: $calculated_pid");
        }
    } else {
        wachthond($extdebug, 2, "ACL PID: Kan geen PID bepalen omdat allpart_array volledig leeg is.");
    }

    // STAP 4: STATUS FLAGS ($ditjaar_array) - Bepaalt of iemand leiding is
    if (empty($ditjaar_array)) {
        
        // Poging 1: Via MEE module (De hoofd-logica)
        if (function_exists('mee_civicrm_configure')) {
            $part_details = ($calculated_pid && function_exists('base_pid2part')) ? base_pid2part($calculated_pid) : NULL;
            
            if ($calculated_pid && !$part_details) {
                wachthond($extdebug, 2, "ACL MEE: PID $calculated_pid gevonden, maar base_pid2part gaf geen details terug.");
            }

            $status_config = function_exists('find_partstatus') ? find_partstatus() : NULL;        
            
            wachthond($extdebug, 3, ">> MEE MODULE START: Berekenen status voor CID $contact_id (PID: " . ($calculated_pid ?? 'GEEN') . ")");
            $ditjaar_array = mee_civicrm_configure($contact_id, $allpart_array, $part_details, $status_config, NULL);
            
            if (empty($ditjaar_array)) {
                wachthond($extdebug, 2, "ACL MEE RESULTAAT: MEE module gaf een lege array terug.");
            } else {
                $l_yes = $ditjaar_array['ditjaarleidyes'] ?? 0;
                wachthond($extdebug, 3, ">> MEE MODULE GELUKT: [LeidYes: $l_yes]");
            }
        } else {
            wachthond($extdebug, 2, "ACL INFO: mee_civicrm_configure niet gevonden, over op Fallback.");
        }

        // Poging 2: Fallback (Eenvoudige telling op basis van inschrijvingstype)
        if (empty($ditjaar_array)) {
            if (empty($allpart_array)) {
                wachthond($extdebug, 1, "ACL FALLBACK ERROR: Status berekening onmogelijk, allpart_array is leeg.");
            } else {
                wachthond($extdebug, 3, ">> START FALLBACK CALCULATIE");
                $count_leid_pos = $allpart_array['result_allpart_pos_leid_count'] ?? 0;
                $count_deel_pos = $allpart_array['result_allpart_pos_deel_count'] ?? 0;
                $count_neg      = $allpart_array['result_allpart_neg_count']      ?? 0;

                $ditjaar_array = [
                    'ditjaarleidyes' => ($count_leid_pos > 0) ? 1 : 0,
                    'ditjaardeelyes' => ($count_deel_pos > 0) ? 1 : 0,
                    'ditjaarleidnot' => ($count_leid_pos == 0 && $count_neg > 0) ? 1 : 0,
                    'ditjaardeelnot' => ($count_deel_pos == 0 && $count_neg > 0) ? 1 : 0,
                    'ditjaardeeltst' => 0, 
                ];
                wachthond($extdebug, 3, ">> FALLBACK RESULTAAT: [LeidYes: " . $ditjaar_array['ditjaarleidyes'] . "]");
            }
        }
    } else {
        wachthond($extdebug, 3, ">> STATUS DATA: \$ditjaar_array was reeds gevuld.");
    }

    // STAP 5: MATRIX DATA (Event Rollen uit Evenementen-matrix)
    if (empty($eventrollen_array)) {
        if (function_exists('acl_helper_get_takenrollen_matrix')) {
            $eventrollen_array = acl_helper_get_takenrollen_matrix($contact_id);
            if (empty($eventrollen_array)) {
                wachthond($extdebug, 2, "ACL MATRIX: Geen rollen gevonden in matrix voor CID $contact_id.");
            } else {
                wachthond($extdebug, 3, ">> MATRIX DATA: " . count($eventrollen_array) . " rollen gevonden.");
            }
        } else {
            wachthond($extdebug, 1, "ACL ERROR: acl_helper_get_takenrollen_matrix bestaat niet.");
        }
    } else {
        wachthond($extdebug, 3, ">> MATRIX DATA: Was al gevuld.");
    }

    // STAP 6: DRUPAL ID (Voor website permissies)
    if (empty($drupal_id)) {
        try {
            $uf_match = civicrm_api3('UFMatch', 'get', ['contact_id' => $contact_id])['values'];
            if (!empty($uf_match)) {
                $drupal_id = $uf_match[0]['uf_id'];
                wachthond($extdebug, 3, ">> DRUPAL ID GEVONDEN: $drupal_id");
            } else {
                wachthond($extdebug, 2, "ACL DRUPAL: Geen UFMatch gevonden voor CID $contact_id (Geen Drupal account).");
            }
        } catch (Exception $e) {
            wachthond($extdebug, 1, "ACL DRUPAL ERROR: " . $e->getMessage());
        }
    } else {
        wachthond($extdebug, 3, ">> DRUPAL ID: Was al gevuld ($drupal_id)");
    }

    wachthond($extdebug,2, 'array_contditjaar', $array_contditjaar);
    wachthond($extdebug,2, 'allpart_array',     $allpart_array);
    wachthond($extdebug,2, 'ditjaar_array',     $ditjaar_array);

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 1.1 VARIABELEN UITPAKKEN");
    wachthond($extdebug,2, "########################################################################");

    $contact_id               = $array_contditjaar['contact_id']            ?? $contact_id; // Fallback naar arg
    $displayname              = $array_contditjaar['displayname']           ?? NULL;
    $laatste_keer             = $array_contditjaar['laatstekeer']           ?? NULL;
    $curcv_keer_deel          = $array_contditjaar['curcv_keer_deel']       ?? NULL;
    $curcv_keer_leid          = $array_contditjaar['curcv_keer_leid']       ?? NULL;
    $datum_belangstelling     = $array_contditjaar['datum_belangstelling']  ?? NULL;

    $email_onvr_email         = $array_contditjaar['email_onvr_email']      ?? NULL;
    $email_home_email         = $array_contditjaar['email_home_email']      ?? NULL;
    $email_priv_email         = (!empty($array_contditjaar['email_priv_email'])) ? $array_contditjaar['email_priv_email'] : $email_home_email;
    $privacy_voorkeuren       = $array_contditjaar['privacy_voorkeuren']    ?? NULL;

    // Haal posities op uit de array
    $ditjaar_pos_kampfunctie  = $allpart_array['result_allpart_pos_kampfunctie']    ?? '';
    $ditjaar_pos_kampkort     = $allpart_array['result_allpart_pos_kampkort']       ?? '';       
    $ditjaar_pos_leid_kampkort= $allpart_array['result_allpart_pos_leid_kampkort']  ?? ''; 
    $ditjaar_pos_part_id      = $allpart_array['result_allpart_pos_part_id']        ?? '';

    // Veiligheidscheck: Zonder ID stoppen we direct
    // We checken nu ook of ditjaar_array gevuld is, om te voorkomen dat we met lege data draaien
    if (!($contact_id > 0 && is_array($ditjaar_array))) {
        wachthond($extdebug, 1, "ACL ABORT: Geen geldig Contact ID of missende data");
        return; 
    }

    // Extract status flags
    $ditjaardeelyes = $ditjaar_array['ditjaardeelyes'] ?? 0; 
    $ditjaardeelnot = $ditjaar_array['ditjaardeelnot'] ?? 0;
    $ditjaarleidyes = $ditjaar_array['ditjaarleidyes'] ?? 0; 
    $ditjaarleidnot = $ditjaar_array['ditjaarleidnot'] ?? 0; 
    $ditjaardeeltst = $ditjaar_array['ditjaardeeltst'] ?? 0; 

    wachthond($extdebug,2, 'ditjaardeelyes',    $ditjaardeelyes);
    wachthond($extdebug,2, 'ditjaardeelmss',    $ditjaardeelmss);
    wachthond($extdebug,2, 'ditjaardeelnot',    $ditjaardeelnot);
    wachthond($extdebug,2, 'ditjaarleidyes',    $ditjaarleidyes);
    wachthond($extdebug,2, 'ditjaarleidmss',    $ditjaarleidmss);
    wachthond($extdebug,2, 'ditjaarleidnot',    $ditjaarleidnot);

    // DEBUG OUTPUT
    wachthond($extdebug,4, "drupal_id",                 $drupal_id);
    wachthond($extdebug,3, "curcv_keer_deel",           $curcv_keer_deel);
    wachthond($extdebug,3, "curcv_keer_leid",           $curcv_keer_leid);
    
    wachthond($extdebug, 3, "ditjaar_pos_kampfunctie",   $ditjaar_pos_kampfunctie);
    wachthond($extdebug, 3, "ditjaar_pos_leid_kampkort", $ditjaar_pos_leid_kampkort);

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 2.0 CONFIGURATIE MAP (De Lookup Array)",         "[$displayname]");
    wachthond($extdebug,2, "########################################################################");
    
    // DIT IS DE BELANGRIJKSTE LIJST VOOR ONDERHOUD
    // Hier koppel je de kamp-code (uit de database) aan de diverse Groep ID's.
    // Nieuw kamp volgend jaar? Voeg hier een regel toe (bijv 'kk3' => [...]).
    
    $camp_map = [
        'kk1' => ['gedrag' => 1766, 'drukwerk' => 1666, 'keuken' => 1675, 'keukenteam' => 1871, 'hl' => 1853, 'kt' => 1883, 'google' => '01baon6m3wo0451'],
        'kk2' => ['gedrag' => 1767, 'drukwerk' => 1667, 'keuken' => 1676, 'keukenteam' => 1872, 'hl' => 1854, 'kt' => 1884, 'google' => '00vx12273fgfnd5'],
        'bk1' => ['gedrag' => 1768, 'drukwerk' => 1668, 'keuken' => 1677, 'keukenteam' => 1873, 'hl' => 1855, 'kt' => 1885, 'google' => '00lnxbz9161bbzw'],
        'bk2' => ['gedrag' => 1769, 'drukwerk' => 1669, 'keuken' => 1678, 'keukenteam' => 1874, 'hl' => 1856, 'kt' => 1886, 'google' => '0147n2zr2s87rx7'],
        'tk1' => ['gedrag' => 1770, 'drukwerk' => 1670, 'keuken' => 1679, 'keukenteam' => 1875, 'hl' => 1857, 'kt' => 1887, 'google' => '02xcytpi1fs7xwo'],
        'tk2' => ['gedrag' => 1771, 'drukwerk' => 1671, 'keuken' => 1680, 'keukenteam' => 1876, 'hl' => 1858, 'kt' => 1888, 'google' => '01opuj5n2028q4s'],
        'jk1' => ['gedrag' => 1772, 'drukwerk' => 1672, 'keuken' => 1681, 'keukenteam' => 1877, 'hl' => 1859, 'kt' => 1889, 'google' => '02bn6wsx3827ior'],
        'jk2' => ['gedrag' => 1773, 'drukwerk' => 1673, 'keuken' => 1682, 'keukenteam' => 1878, 'hl' => 1860, 'kt' => 1890, 'google' => '030j0zll0m5pg5h'],
        'top' => ['gedrag' => 1774, 'drukwerk' => 1674, 'keuken' => 1683, 'keukenteam' => 1879, 'hl' => 1861, 'kt' => 1891, 'google' => '00haapch3zvbjru'],
    ];

    // Algemene Groep ID's (Constanten)
    $gid_keukenstaf_algemeen    = 1882;
    $gid_topkamp_algemeen       = 1756;
    $gid_ditjaardeel            = 1846;
    $gid_alleleiding            = 1849; // Ditjaar Alle Leiding
    $gid_groepsleiding          = 1850;
    $gid_teamspecials_algemeen  = 457;
    $gid_kampstaf               = 456;
    $gid_kernteam               = 1842;
    $gid_hoofdleiding           = 1976;
    $gid_bestuur                = 455;
    $gid_belangstelling         = 855;

    // Bepaal de huidige config op basis van de kampcode van de persoon
    $current_camp = $camp_map[$ditjaar_pos_leid_kampkort] ?? null;
    
    // Debug logging voor config
    if ($current_camp) {
        wachthond($extdebug, 2, "Configuratie gevonden voor kamp", "$ditjaar_pos_leid_kampkort");
    } else {
        wachthond($extdebug, 2, "Geen specifieke kamp-configuratie (is normaal voor niet-leiding)", "Code: $ditjaar_pos_leid_kampkort");
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 3.0 MEMBERSHIP LOGICA (PRESALE)");
    wachthond($extdebug,2, "########################################################################");

    // Peildatum logica: 1 augustus is de knip voor het nieuwe seizoen.
    // Variabele is 'static' zodat we dit maar 1x berekenen per run.
    static $augustusDeadline = NULL;
    if ($augustusDeadline === NULL) {
        $augustusDeadline = new DateTime('first day of August');
        if ($augustusDeadline > new DateTime()) {
            $augustusDeadline->modify('-1 year');
        }
    }
    // Is de persoon dit "boekjaar" mee geweest?
    $isJuisteJaar = ((int)$laatste_keer === (int)$augustusDeadline->format('Y'));

    // Check huidig membership status
    $params_membership_get = [
      'checkPermissions' => FALSE,
      'select' => ['id'],
      'where' => [
          ['contact_id', '=', $contact_id],
          ['status_id:label', '=', 'Actief'],
          ['membership_type_id:label', '=', 'presalemember'],
      ],
    ];
    $membership_id = civicrm_api4('Membership','get', $params_membership_get)->first()['id'] ?? NULL;

    // Actie: Aanmaken of Verwijderen
    if ($isJuisteJaar && !$membership_id) {
        civicrm_api4('Membership','create', [
            'checkPermissions' => FALSE,
            'values' => [
                'contact_id' => $contact_id,
                'membership_type_id:label' => 'presalemember',
                'status_id:label' => 'Actief',
            ],
        ]);
        wachthond($extdebug,3, "Membership presale toegekend", $displayname);
    } elseif (!$isJuisteJaar && $membership_id) {
        civicrm_api4('Membership','delete', [
            'checkPermissions' => FALSE,
            'where' => [['id', '=', $membership_id]],
        ]);
        wachthond($extdebug,3, "Membership presale verwijderd", $displayname);
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 4.0 ALGEMENE CLEANUP");
    wachthond($extdebug,2, "########################################################################");

    // Als iemand vorig jaar mee was, maar dit jaar niet, moeten we opruimen.
    
    if ($ditjaardeelnot == 1) {
        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.1 ALGEMENE CLEANUP DEELNEMER (NIET MEE DIT JAAR)");
        wachthond($extdebug,2, "########################################################################");

        acl_group_remove($contact_id, [$gid_topkamp_algemeen, $gid_ditjaardeel], 'cleanup_deel_algemeen');
        cms_rol_remove($drupal_id, $displayname, 'ditjaar_deelnemer');
    }

    if ($ditjaarleidnot == 1 AND $ditjaardeeltst == 0) {
        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.2 ALGEMENE CLEANUP LEIDING (NIET MEE DIT JAAR)");
        wachthond($extdebug,2, "########################################################################");

        // Verwijder uit algemene leiding groepen
        acl_group_remove($contact_id, [
            $gid_alleleiding, $gid_groepsleiding, $gid_kernteam, $gid_hoofdleiding, $gid_keukenstaf_algemeen
        ], 'cleanup_leid_algemeen');
        
        // Verwijder Drupal rollen
        $rollen_te_verwijderen = ['ditjaar_hoofdleiding', 'ditjaar_keukenhoofd', 'ditjaar_keukenteam', 'ditjaar_kernteam', 'ditjaar_alleleiding', 'ditjaar_groepsleiding'];
        foreach($rollen_te_verwijderen as $rol) cms_rol_remove($drupal_id, $displayname, $rol);
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 5.0 OOIT GROEPEN (BELANGSTELLING/HISTORIE)");
    wachthond($extdebug,2, "########################################################################");
    
    // A. Belangstelling (Gebaseerd op datum veld)
    $acl_belangstelling = ['aclgroup' => $gid_belangstelling, 'cmsrol' => 'ooit_belangstelling', 'acl_group_label' => 'belangstelling'];
    if ($datum_belangstelling) {
        permissions_add($contact_id, $drupal_id, $displayname, $acl_belangstelling);
    } else {
        permissions_rem($contact_id, $drupal_id, $displayname, $acl_belangstelling);
    }

    // B. Ooit Deelnemer/Leiding (Gebaseerd op Curriculum tellers)
    // CMS Rol: Ooit Deelnemer
    if ($curcv_keer_deel > 0) cms_rol_add($drupal_id, $displayname, 'ooit_deelnemer');
    else cms_rol_remove($drupal_id, $displayname, 'ooit_deelnemer');

    // CMS Rol: Ooit Leiding
    if ($curcv_keer_leid > 0) cms_rol_add($drupal_id, $displayname, 'ooit_leiding');
    else cms_rol_remove($drupal_id, $displayname, 'ooit_leiding');

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 6.0 DIT JAAR ALGEMENE GROEPEN (ALLE LEIDING / GL)");
    wachthond($extdebug,2, "########################################################################");

    // A. Ditjaar Alle Leiding
    $acl_alleleiding = ['aclgroup' => $gid_alleleiding, 'cmsrol' => 'ditjaar_alleleiding', 'acl_group_label' => 'alleleiding'];
    if ($ditjaarleidyes == 1) {
        permissions_add($contact_id, $drupal_id, $displayname, $acl_alleleiding);
    } else {
        permissions_rem($contact_id, $drupal_id, $displayname, $acl_alleleiding);
    }

    // B. Ditjaar Groepsleiding
    $acl_groepsleiding = ['aclgroup' => $gid_groepsleiding, 'cmsrol' => 'ditjaar_groepsleiding', 'acl_group_label' => 'groepsleiding'];
    if ($ditjaarleidyes == 1 && $ditjaar_pos_leid_kampfunctie == 'groepsleiding') {
        permissions_add($contact_id, $drupal_id, $displayname, $acl_groepsleiding);
    } else {
        permissions_rem($contact_id, $drupal_id, $displayname, $acl_groepsleiding);
    }

    if ($curcv_keer_leid > 0) {
        
        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 7.0 SPECIFIEKE ROL SYNC (EVENT VERIFICATIE)");
        wachthond($extdebug,2, "########################################################################");

        // Hier vindt de magie plaats: We syncen de gebruiker naar de juiste sub-groep
        // (bijv. 'kk1-gedrag') en verwijderen hem uit alle andere opties.

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 7.1 SYNC GEDRAGSGROEPEN (ONLY EVENT CHECK)", "[$displayname]");
        wachthond($extdebug,2, "########################################################################");

        // A. Configuratie ophalen
        $all_gedrag_ids     = array_column($camp_map, 'gedrag');
        $camp_gedrag_id     = $current_camp['gedrag'] ?? NULL;
        
        wachthond($extdebug, 3, "7.1 DEBUG - Config", "Huidig Kamp ID: " . ($camp_gedrag_id ?? 'GEEN'));

        // B. Check: Event Velden (Dit is nu de ENIGE manier om toegang te krijgen)
        // AANGEPAST: 1 per regel en nieuwe keys
        $check_ids_gedrag = [
            $eventrollen_array['event_gedrag0_id'], 
            $eventrollen_array['event_gedrag1_id'], 
            $eventrollen_array['event_gedrag2_id']
        ];
        
        // Is de persoon ingevuld bij de gedragsrollen in het event?
        $is_gedrag_coord = in_array($contact_id, $check_ids_gedrag);

        wachthond($extdebug, 3, "7.1 DEBUG - Check Event Velden", [
            'Mijn ID'            => $contact_id,
            'Gevonden?'          => $is_gedrag_coord ? 'JA' : 'NEE',
            'Velden IDs'         => implode(',', array_filter($check_ids_gedrag))
        ]);

        // C. Target Bepalen (Specifieke Kamp Groep)
        // Je moet een kamp hebben ($camp_gedrag_id) EN in het event staan ($is_gedrag_coord)
        $target_gedrag = ($camp_gedrag_id && $is_gedrag_coord) ? $camp_gedrag_id : NULL;

        wachthond($extdebug, 1, "7.1 CONCLUSIE SPECIFIEKE GROEP", [
            'Kamp Bekend?'      => $camp_gedrag_id ? 'JA' : 'NEE',
            'In Event?'         => $is_gedrag_coord ? 'JA' : 'NEE',
            '>>> TARGET GROUP'  => $target_gedrag ?? 'GEEN (Wordt verwijderd uit alle gedragsgroepen)'
        ]);
        
        // 1. Voer de Sync uit voor specifieke groepen (bijv. gedrag-kk1)
        acl_group_sync($contact_id, $target_gedrag, $all_gedrag_ids, 'gedrag');
        
        // D. Algemene Groep (457) & CMS Rol Logic
        // Als je in EEN event als gedrag staat, mag je ook in de algemene groep.
        $acl_teamspecials_alg = ['aclgroup' => $gid_teamspecials_algemeen, 'cmsrol' => 'ditjaar_teamspecials', 'acl_group_label' => 'teamspecials_algemeen'];
        
        // Logic: Als je een target hebt (dus gekoppeld aan een kamp) OF in het event staat (als het kamp nog niet bekend is)
        $should_have_general = ($target_gedrag || $is_gedrag_coord);
        
        wachthond($extdebug, 1, "7.1 CONCLUSIE ALGEMENE GROEP ($gid_teamspecials_algemeen)", [
            '>>> ACTIE'         => $should_have_general ? 'TOEVOEGEN (Indien nodig)' : 'VERWIJDEREN'
        ]);

        if ($should_have_general) {
             permissions_add($contact_id, $drupal_id, $displayname, $acl_teamspecials_alg);
        } else {
             permissions_rem($contact_id, $drupal_id, $displayname, $acl_teamspecials_alg);
        }
        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 7.2 SYNC DRUKWERK", "[$displayname]");
        wachthond($extdebug,2, "########################################################################");
        
        $all_drukwerk_ids = array_column($camp_map, 'drukwerk');
        $camp_drukwerk_id = $current_camp['drukwerk'] ?? NULL;
        
        // Verificatie met Event Data (AANGEPAST):
        $is_boekje_coord = in_array($contact_id, [
            $eventrollen_array['event_boekje0_id'],
            $eventrollen_array['event_boekje1_id'],
            $eventrollen_array['event_boekje2_id']
        ]);
        
        $target_drukwerk = ($camp_drukwerk_id && $is_boekje_coord) ? $camp_drukwerk_id : NULL;

        wachthond($extdebug,1, "Check Drukwerk", "Event: " . ($is_boekje_coord ? 'JA' : 'NEE') . " -> Target: " . ($target_drukwerk ?? 'GEEN'));
        
        acl_group_sync($contact_id, $target_drukwerk, $all_drukwerk_ids, 'drukwerk');
        
        if ($target_drukwerk) cms_rol_add($drupal_id, $displayname, 'ditjaar_drukwerk');
        else cms_rol_remove($drupal_id, $displayname, 'ditjaar_drukwerk');

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 7.3 SYNC KEUKENCHEF", "[$displayname]");
        wachthond($extdebug,2, "########################################################################");
        
        $all_keuken_ids = array_column($camp_map, 'keuken');
        $camp_keuken_id = $current_camp['keuken'] ?? NULL;

        // Verificatie met Event Data (AANGEPAST):
        $is_in_event_keuken = in_array($contact_id, [
            $eventrollen_array['event_keuken0_id'],
            $eventrollen_array['event_keuken1_id'],
            $eventrollen_array['event_keuken2_id'],
            $eventrollen_array['event_keuken3_id']
        ]);
        
        // Check ook op functie (voor de zekerheid)
        $is_functie_hoofd = ($ditjaar_pos_leid_kampfunctie == 'hoofdkeuken');

        // Uitzondering Daniel Fritschij
        if ($contact_id == 19210) { 
            // Daniel krijgt alles
            foreach ($all_keuken_ids as $gid) {
                permissions_add($contact_id, $drupal_id, $displayname, ['aclgroup' => $gid, 'acl_group_label' => 'KeukenChef (Daniel)']);
            }
            $target_keuken = NULL; // Sync hoeft niet meer, is hierboven handmatig gedaan
        } else {
            // Normale check: Zit in event velden OF heeft de functie
            $target_keuken = ($camp_keuken_id && ($is_in_event_keuken || $is_functie_hoofd)) ? $camp_keuken_id : NULL;
            
            wachthond($extdebug,1, "Check Keukenchef", "Event: " . ($is_in_event_keuken ? 'JA' : 'NEE') . " -> Target: " . ($target_keuken ?? 'GEEN'));
            
            acl_group_sync($contact_id, $target_keuken, $all_keuken_ids, 'keukenchef');
        }
        
        if ($target_keuken || $contact_id == 19210) cms_rol_add($drupal_id, $displayname, 'ditjaar_keukenhoofd');
        else cms_rol_remove($drupal_id, $displayname, 'ditjaar_keukenhoofd');

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 7.4 SYNC KEUKENTEAM SPECIFIEK", "[$displayname]");
        wachthond($extdebug,2, "########################################################################");
        
        $all_kt_ids = array_column($camp_map, 'keukenteam');
        $camp_kt_id = $current_camp['keukenteam'] ?? NULL;
        
        $is_keuken_functie = in_array($ditjaar_pos_leid_kampfunctie, ['hoofdkeuken', 'keukenteamlid']);
        
        // Hier vertrouwen we op de functie-rol, niet op specifieke event-velden (want er zijn geen velden voor alle leden)
        $target_kt = ($camp_kt_id && $is_keuken_functie) ? $camp_kt_id : NULL;

        wachthond($extdebug,1, "Check Keukenteam", "Functie: " . ($is_keuken_functie ? 'JA' : 'NEE'));

        acl_group_sync($contact_id, $target_kt, $all_kt_ids, 'keukenteam');

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 7.5 SYNC KEUKENTEAM ALGEMEEN", "[$displayname]");
        wachthond($extdebug,2, "########################################################################");
        
        $acl_kt_algemeen = ['aclgroup' => $gid_keukenstaf_algemeen, 'cmsrol' => 'ditjaar_keukenteam', 'acl_group_label' => 'keukenstaf_algemeen'];
        if ($is_keuken_functie) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_kt_algemeen);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_kt_algemeen);
        }        
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 8.0 STAF FUNCTIES (HOOFDLEIDING & KERNTEAM)", "[$displayname]");
    wachthond($extdebug,2, "########################################################################");
    
    $group_staf_member = acl_group_get($contact_id, $gid_kampstaf, 'check')['group_member'];

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 8.1 HOOFDLEIDING (SPECIFIEK & ALGEMEEN)", "[$displayname]");
    wachthond($extdebug,2, "########################################################################");

    // 1. Config & Inputs
    $all_hl_ids = array_column($camp_map, 'hl');
    $camp_hl_id = $current_camp['hl'] ?? NULL;
    
    wachthond($extdebug, 3, "8.1 DEBUG - Config", "Specifieke Kamp Groep ID: " . ($camp_hl_id ?? 'GEEN'));

    // 2. Checks (AANGEPAST)
    $check_ids_hl = [
        $eventrollen_array['event_hldn1_id'], 
        $eventrollen_array['event_hldn2_id'], 
        $eventrollen_array['event_hldn3_id']
    ];
    $is_in_event_hl = in_array($contact_id, $check_ids_hl);

    wachthond($extdebug, 3, "8.1 DEBUG - Check 1: Event Velden", [
        'Mijn ID' => $contact_id,
        'Gevonden in velden?' => $is_in_event_hl ? 'JA' : 'NEE'
    ]);

    $is_functie_hl = ($ditjaar_pos_kampfunctie == 'hoofdleiding');
    
    wachthond($extdebug, 3, "8.1 DEBUG - Check 2: Functie", [
        'Mijn Functie' => $ditjaar_pos_kampfunctie, // <-- Deze moet nu 'hoofdleiding' tonen
        'Verwacht' => 'hoofdleiding',
        'Match?' => $is_functie_hl ? 'JA' : 'NEE'
    ]);
    
    // 3. Conclusie: ALLES moet WAAR zijn
    $is_valid_hl = ($group_staf_member && $is_in_event_hl && $is_functie_hl);

    wachthond($extdebug, 1, "8.1 CONCLUSIE LOGICA", [
        '1. Lid Kampstaf (456)?' => $group_staf_member ? 'JA' : 'NEE',
        '2. Staat in Event?' => $is_in_event_hl ? 'JA' : 'NEE',
        '3. Heeft Functie?' => $is_functie_hl ? 'JA' : 'NEE',
        '>>> TOTAAL OORDEEL' => $is_valid_hl ? 'GELDIG' : 'ONGELDIG'
    ]);

    // 4. Actie A: Specifieke Kamp Groep Sync
    $target_hl = ($camp_hl_id && $is_valid_hl) ? $camp_hl_id : NULL;
    acl_group_sync($contact_id, $target_hl, $all_hl_ids, 'specifieke_hl');

    // 5. Actie B: Algemene Groep
    $acl_hl_algemeen = ['aclgroup' => $gid_hoofdleiding, 'cmsrol' => 'ditjaar_hoofdleiding', 'acl_group_label' => 'hoofdleiding_algemeen'];
    if ($is_valid_hl) {
        permissions_add($contact_id, $drupal_id, $displayname, $acl_hl_algemeen);
    } else {
        permissions_rem($contact_id, $drupal_id, $displayname, $acl_hl_algemeen);
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 8.2 KERNTEAM (SPECIFIEK & ALGEMEEN)", "[$displayname]");
    wachthond($extdebug,2, "########################################################################");

    // 1. Config & Inputs
    $all_kt_spec_ids = array_column($camp_map, 'kt');
    $camp_kt_spec_id = $current_camp['kt'] ?? NULL;

    wachthond($extdebug, 3, "8.2 DEBUG - Config", "Specifieke Kamp Groep ID: " . ($camp_kt_spec_id ?? 'GEEN'));

    // 2. Checks (AANGEPAST)
    $check_ids_kt = [
        $eventrollen_array['event_kern1_id'], 
        $eventrollen_array['event_kern2_id'], 
        $eventrollen_array['event_kern3_id']
    ];
    $is_in_event_kt = in_array($contact_id, $check_ids_kt);
    
    wachthond($extdebug, 3, "8.2 DEBUG - Check 1: Event Velden", [
        'Mijn ID' => $contact_id,
        'Gevonden in velden?' => $is_in_event_kt ? 'JA' : 'NEE'
    ]);

    $allowed_functions_kt = ['kernteam', 'kernteamlid'];
    
    $is_functie_kt = in_array($ditjaar_pos_kampfunctie, $allowed_functions_kt);

    wachthond($extdebug, 3, "8.2 DEBUG - Check 2: Functie", [
        'Mijn Functie' => $ditjaar_pos_kampfunctie, // <-- Deze toont nu de functie
        'Toegestaan' => implode(',', $allowed_functions_kt),
        'Match?' => $is_functie_kt ? 'JA' : 'NEE'
    ]);

    // 3. Conclusie: ALLES moet WAAR zijn
    $is_valid_kt = ($group_staf_member && $is_in_event_kt && $is_functie_kt);

    wachthond($extdebug, 1, "8.2 CONCLUSIE LOGICA", [
        '1. Lid Kampstaf (456)?' => $group_staf_member ? 'JA' : 'NEE',
        '2. Staat in Event?'     => $is_in_event_kt ? 'JA' : 'NEE',
        '3. Heeft Functie?'      => $is_functie_kt ? 'JA' : 'NEE',
        '>>> TOTAAL OORDEEL'     => $is_valid_kt ? 'GELDIG' : 'ONGELDIG'
    ]);

    // 4. Actie A: Specifieke Kamp Groep Sync
    $target_kt_spec = ($camp_kt_spec_id && $is_valid_kt) ? $camp_kt_spec_id : NULL;
    acl_group_sync($contact_id, $target_kt_spec, $all_kt_spec_ids, 'specifieke_kt');

    // 5. Actie B: Algemene Groep
    $acl_kt_algemeen = ['aclgroup' => $gid_kernteam, 'cmsrol' => 'ditjaar_kernteam', 'acl_group_label' => 'kernteam_algemeen'];
    if ($is_valid_kt) {
        permissions_add($contact_id, $drupal_id, $displayname, $acl_kt_algemeen);
    } else {
        permissions_rem($contact_id, $drupal_id, $displayname, $acl_kt_algemeen);
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 9.0 EVENT REGISTRATIE & EMAILS");
    wachthond($extdebug,2, "########################################################################");

    $is_bestuur = acl_group_get($contact_id, $gid_bestuur, 'check')['group_member'];

    if ($is_bestuur || $group_staf_member || $ditjaarleidyes) {
        
        $events_cache = find_eventids();
        $kampids_toer = $events_cache['toer'] ?? [];
        
        // Haal bestaande registraties op
        $array_cv = cv_civicrm_configure($contact_id, $array_contditjaar, $ditjaar_array);
        $evtcv_toer_array = $array_cv['evtcv_toer_array'] ?? [];

        // Bereken verschil (welke missen we nog?)
        $missing = array_diff($kampids_toer, $evtcv_toer_array);
        
        foreach ($missing as $eid) {
            if ($eid > 0) {
                civicrm_api4('Participant','create', [
                    'checkPermissions' => FALSE,
                    'values' => [
                        'contact_id'    => $contact_id,
                        'event_id'      => $eid,
                        'register_date' => $today_datetime,
                        'status_id'     => 24, // Status: In afwachting
                        'role_id'       => [7], // Rol: Deelnemer
                    ]
                ]);
                wachthond($extdebug,3, "Geregistreerd voor Toerusting Event", $eid);
            }
        }
    }

    // -------------------------------------------------------------------------
    // 9.1 EMAIL NOTIFICATIES (Alleen voor HL/Kernteam)
    // -------------------------------------------------------------------------
    if ($group_staf_member && in_array($ditjaar_pos_leid_kampfunctie, ['hoofdleiding', 'kernteamlid'])) {

        wachthond($extdebug, 2, "########################################################################");
        wachthond($extdebug, 1, "### ACL 9.1 EMAIL NOTIFICATIES UPDATE VOOR $displayname");
        wachthond($extdebug, 2, "########################################################################");

        // Prioriteit: Participant settings > Contact settings > Default (privemail)
        $notif_map = [
            'notif_deel' => $array_contditjaar['cont_notificatie_deel'] ?? 'privemail',
            'notif_leid' => $array_contditjaar['cont_notificatie_leid'] ?? 'privemail',
            'notif_kamp' => $array_contditjaar['cont_notificatie_kamp'] ?? 'privemail',
            'notif_staf' => $array_contditjaar['cont_notificatie_staf'] ?? 'privemail',
        ];

        // 1. Maak/Update CiviCRM E-mails (Location Types)
        foreach ($notif_map as $type_label => $voorkeur) {
            $target_email = NULL;
            if ($voorkeur == 'kamppers') {
                $target_email = $email_onvr_email;
            } elseif ($voorkeur == 'privemail') {
                $target_email = $email_priv_email;
            }

            if ($target_email) {
                $params_email_save = [
                    'checkPermissions' => FALSE,
                    'records' => [[
                        'location_type_id:name' => $type_label,
                        'email'       => $target_email,
                        'contact_id' => $contact_id,
                        'is_primary' => FALSE,
                    ]],
                    'match' => ['contact_id', 'location_type_id'],
                ];
                if ($extwrite == 1 && !in_array($privacy_voorkeuren, ["33", "44"])) {
                    civicrm_api4('Email', 'save', $params_email_save);
                    wachthond($extdebug, 2, "$type_label opgeslagen", $target_email);
                }
            }
        }

        // 2. Update Participant Record (indien aanwezig)
        if ($ditjaar_pos_part_id) { 
            $params_part_notif = [
                'checkPermissions' => FALSE,
                'where'  => [['id', '=', $ditjaar_pos_part_id]],
                'values' => [
                    'PART_LEID_HOOFD.notificatie_deel' => $notif_map['notif_deel'], 
                    'PART_LEID_HOOFD.notificatie_leid' => $notif_map['notif_leid'], 
                    'PART_LEID_HOOFD.notificatie_kamp' => $notif_map['notif_kamp'], 
                    'PART_LEID_HOOFD.notificatie_staf' => $notif_map['notif_staf'],
                ],
            ];
            if ($extwrite == 1) {
                civicrm_api4('Participant', 'update', $params_part_notif);
                wachthond($extdebug, 2, "Participant notif fields geupdate", $ditjaar_pos_part_id);
            }
        }
    }

    wachthond($extdebug,1, "### ACL - EINDE CONFIG VOOR $displayname");
}

/**
 * ============================================================================
 * HELPER: GROUP REMOVE (MET HISTORIE BEHOUD)
 * ============================================================================
 */
function acl_group_remove($contactid, $group, $group_label) {

    $extdebug       = 0;
    $apidebug       = FALSE;
    $extwrite       = 1;

    $contact_id     = $contactid;
    $group_ids      = is_array($group) ? $group : [$group];

    wachthond($extdebug, 4, "contact_id",    $contact_id);
    wachthond($extdebug, 4, "group_array",   $group_ids);

    if ($contact_id AND !empty($group_ids)) {

        // STAP 1: CHECK (Welke van deze groepen zijn NU actief 'Added'?)
        $results = civicrm_api4('GroupContact', 'get', [
            'checkPermissions' => FALSE,
            'select' => ['id', 'group_id'], // We hebben het unieke ID nodig voor de update
            'where' => [
                ['contact_id', '=', $contact_id],
                ['group_id', 'IN', $group_ids],
                ['status', '=', 'Added'] 
            ]
        ]);

        $ids_to_remove = [];
        $groups_found  = [];

        foreach ($results as $row) {
            $ids_to_remove[] = $row['id'];       // Het unieke koppel-ID
            $groups_found[]  = $row['group_id']; // Voor de log
        }

        // STAP 2: UPDATE NAAR REMOVED
        if (!empty($ids_to_remove)) {
            wachthond($extdebug, 3, "########################################################################");
            wachthond($extdebug, 3, "### ACL - VERWIJDER CID $contact_id UIT GROEPEN $group_label",  $groups_found);
            wachthond($extdebug, 3, "########################################################################");

            if ($extwrite == 1) {
                // AANPASSING: Update status naar 'Removed' zodat historie bewaard blijft
                foreach($ids_to_remove as $record_id) {
                    $result_remove = civicrm_api4('GroupContact', 'update', [
                        'checkPermissions' => FALSE,
                        'where' => [['id', '=', $record_id]],
                        'values' => ['status' => 'Removed']
                    ]);
                }
                
                wachthond($extdebug, 1, 'Verwijderd uit ACL groepen (Status Removed)', implode(',', $groups_found));
            }
        } else {
            wachthond($extdebug, 4, 'Verwijderd uit ACL groep [SKIPPED - WAS GEEN LID]', $group_label);
        }
    }
}

// acl_group_update en acl_group_create zijn feitelijk aliases voor permissions_add 
// maar worden behouden voor backward compatibility als ze elders worden aangeroepen.
function acl_group_update($contactid, $group_id, $group_label) {
    permissions_add($contactid, NULL, "Unknown", ['aclgroup' => $group_id, 'acl_group_label' => $group_label]);
}

function acl_group_create($contactid, $group_id, $group_label) {
    permissions_add($contactid, NULL, "Unknown", ['aclgroup' => $group_id, 'acl_group_label' => $group_label]);
}

/**
 * ============================================================================
 * HELPER: GET GROUP STATUS (MET CACHE)
 * ============================================================================
 * Haalt efficiënt groepslidmaatschappen op.
 * Gebruikt een statische cache om te voorkomen dat we voor elke check de DB belasten.
 * LET OP: De cache wordt niet invalid bij writes! Daarom gebruiken write functies (add/rem) een live check.
 */
function acl_group_get($contactid, $group_id, $group_label) {

    $extdebug = 0;
    
    // 1. Statische cache: Dit blijft in het geheugen zolang het script draait (per contact)
    static $contact_groups_cache = [];

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

function cms_rol_check($drupal_id, $displayname, $cmsrol) {

    $extdebug    = 0;
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

    $extdebug       = 0;           

    wachthond($extdebug,1, 'drupal_id',     $drupal_id);
    wachthond($extdebug,1, 'displayname',   $displayname);

    if (!$drupal_id OR !$cmsrol) {
        wachthond($extdebug,1, 'cms_rol_add', 'input parameters missing');
        return;
    }

    // Double check: heeft hij de rol al?
    if (cms_rol_check($drupal_id, $displayname, $cmsrol) == 1) {
        return; // Niets doen
    }

    wachthond($extdebug,3, "########################################################################");
    wachthond($extdebug,3, "### ACL - TOEVOEGEN ROL $cmsrol AAN UID $drupal_id",          $displayname);
    wachthond($extdebug,3, "########################################################################");

    if ($role = user_role_load_by_name($cmsrol)) {
        user_multiple_role_edit(array($drupal_id), 'add_role', $role->rid);
    }
}

function cms_rol_remove($drupal_id, $displayname, $cmsrol) {

    $extdebug       = 0;           

    wachthond($extdebug,1, 'drupal_id',     $drupal_id);
    wachthond($extdebug,1, 'displayname',   $displayname);

    if (!$drupal_id OR !$cmsrol) {
        wachthond($extdebug,1, 'cms_rol_remove', 'input parameters missing');        
        return;
    }

    // Double check: heeft hij de rol wel? Zo niet, dan hoeven we niks te doen.
    if (cms_rol_check($drupal_id, $displayname, $cmsrol) == 0) {
        return; 
    }

    wachthond($extdebug,3, "########################################################################");
    wachthond($extdebug,3, "### ACL - VERWIJDEREN ROL $cmsrol VAN UID $drupal_id",        $displayname);
    wachthond($extdebug,3, "########################################################################");

    if ($role = user_role_load_by_name($cmsrol)) {
        user_multiple_role_edit(array($drupal_id), 'remove_role', $role->rid);
    }
}

/**
 * ============================================================================
 * HELPER: PERMISSIONS ADD (ROBUUSTE VERSIE)
 * ============================================================================
 * Voegt toe. Voorkomt "Duplicate Entry" fouten door te checken of er al een
 * (verwijderde) regel bestaat. Zo ja -> Update. Zo nee -> Create.
 */
function permissions_add($contact_id, $drupal_id, $displayname, $acl_array) {

    $extdebug = 0; // 1 = basic // 2 = verbose // 3 = params // 4 = results

    // 1. Variabelen uitpakken
    $aclgroup             = $acl_array['aclgroup']          ?? NULL;
    $cmsrol               = $acl_array['cmsrol']            ?? NULL;
    $rolname              = $acl_array['rolname']           ?? NULL;
    $acl_group_label      = $acl_array['acl_group_label']   ?? NULL;
    $cms_hasrol           = $acl_array['cms_hasrol']        ?? NULL;

    if (empty($contact_id) || empty($aclgroup)) {
        return;
    }

    // -------------------------------------------------------------------------
    // STAP 1: HAAL HET ID OP (Cruciaal tegen duplicates)
    // -------------------------------------------------------------------------
    // We moeten weten of er al een regel bestaat (ook al is die 'Removed')
    // zodat we die kunnen updaten in plaats van een nieuwe inserten.
    
    $check = civicrm_api4('GroupContact', 'get', [
        'checkPermissions' => FALSE,
        'select' => ['id', 'status'], // <--- We hebben het ID nodig!
        'where' => [
            ['contact_id', '=', $contact_id],
            ['group_id', '=', $aclgroup]
        ],
        'limit' => 1
    ])->first();

    $real_status = $check['status'] ?? 'None';
    $link_id     = $check['id']     ?? NULL;

    // -------------------------------------------------------------------------
    // STAP 2: ACTIE BEPALEN
    // -------------------------------------------------------------------------
    
    // Scenario A: Al lid. Niets doen.
    if ($real_status === 'Added') {
        // wachthond($extdebug, 3, "Skipped ACL add (already member)", "$displayname -> $aclgroup");
    }
    
    // Scenario B: Bestaat wel (Removed/Pending), dus UPDATE.
    elseif ($link_id) {
        
        wachthond($extdebug, 1, "### ACL - UPDATE ACTIE: $displayname -> Groep $acl_group_label ($aclgroup)", "[Was: $real_status, ID: $link_id]");
        
        $result = civicrm_api4('GroupContact', 'update', [
            'checkPermissions' => FALSE,
            'where' => [['id', '=', $link_id]], // Update op basis van primair ID
            'values' => ['status' => 'Added']
        ]);
        wachthond($extdebug, 9, 'API Result GroupContact Update', $result);
    }
    
    // Scenario C: Bestaat niet, dus CREATE.
    else {
        
        wachthond($extdebug, 1, "### ACL - CREATE ACTIE: $displayname -> Groep $acl_group_label ($aclgroup)", "[Nieuw]");
        
        $result = civicrm_api4('GroupContact', 'create', [
            'checkPermissions' => FALSE,
            'values' => [
                'contact_id' => $contact_id,
                'group_id'   => $aclgroup,
                'status'     => 'Added'
            ]
        ]);
        wachthond($extdebug, 9, 'API Result GroupContact Create', $result);
    }

    // -------------------------------------------------------------------------
    // STAP 3: DRUPAL ROL (CMS)
    // -------------------------------------------------------------------------
    
    if ($drupal_id > 0 && !empty($cmsrol)) {
        if ($cms_hasrol != 1) {
            cms_rol_add($drupal_id, $displayname, $cmsrol);
        }
    }
}

/**
 * ============================================================================
 * HELPER: PERMISSIONS REMOVE (MET HISTORIE BEHOUD)
 * ============================================================================
 */
function permissions_rem($contact_id, $drupal_id, $displayname, $acl_array) {

    $extdebug = 0; 

    $aclgroup             = $acl_array['aclgroup']          ?? NULL;
    $cmsrol               = $acl_array['cmsrol']            ?? NULL;
    $rolname              = $acl_array['rolname']           ?? NULL;
    $acl_group_label      = $acl_array['acl_group_label']   ?? NULL;
    $cms_hasrol           = $acl_array['cms_hasrol']        ?? NULL;

    if (empty($contact_id) || empty($aclgroup)) {
        return;
    }

    // STAP 1: ID en Status ophalen
    $check = civicrm_api4('GroupContact', 'get', [
        'checkPermissions' => FALSE,
        'select' => ['id', 'status'],
        'where' => [
            ['contact_id', '=', $contact_id],
            ['group_id', '=', $aclgroup]
        ],
        'limit' => 1
    ])->first();

    $real_status = $check['status'] ?? 'None';
    $link_id     = $check['id']     ?? NULL;

    // STAP 2: Alleen actie ondernemen als ze nu lid ('Added') of hangend ('Pending') zijn.
    if ($link_id && in_array($real_status, ['Added', 'Pending'])) {

        wachthond($extdebug, 1, "### ACL - REMOVE ACTIE: $displayname uit Groep $acl_group_label ($aclgroup)", "[Was: $real_status]");

        // AANPASSING: We gebruiken UPDATE naar 'Removed' i.p.v. DELETE.
        // Hierdoor blijft hij zichtbaar in de historie ('Verwijderde groepen').
        $result = civicrm_api4('GroupContact', 'update', [
            'checkPermissions' => FALSE,
            'where' => [['id', '=', $link_id]],
            'values' => ['status' => 'Removed']
        ]);
        
        wachthond($extdebug, 9, 'API Result GroupContact Set Removed', $result);
    } 

    // STAP 3: DRUPAL ROL
    if ($drupal_id > 0 && !empty($cmsrol)) {
        if (cms_rol_check($drupal_id, $displayname, $cmsrol) == 1) {
            cms_rol_remove($drupal_id, $displayname, $cmsrol);
        }
    }
}

function googlegroup_subscribe($googlegroup_id, $googlegroup_batch) {

    $extdebug = 0;

    $params_googlegroup_subscribe = [
        'group_id'      => $googlegroup_id,
        'emails'        => $googlegroup_batch,
    ];
    
    try {
        wachthond($extdebug, 2, 'params_googlegroup_subscribe', $params_googlegroup_subscribe);
        
        // We gebruiken @ om eventuele PHP warnings te onderdrukken die de API soms gooit
        $result = @civicrm_api3('Googlegroups', 'subscribe', $params_googlegroup_subscribe);
        
        wachthond($extdebug, 3, 'result_googlegroup_subscribe', $result);
    } 
    catch (\Throwable $e) { 
        // Gevangen! Zowel Exceptions als Fatale Errors (zoals TypeError)
        $msg = $e->getMessage();
        wachthond($extdebug, 1, "GOOGLE API FOUT (Subscribe)", $msg);
    }
}

function googlegroup_deletemember($googlegroup_id, $googlegroup_batch) {

    $extdebug = 0;

    $params_googlegroup_deletemember = [
        'group_id'      => $googlegroup_id,
        'member'        => $googlegroup_batch,
    ];
    
    try {
        wachthond($extdebug, 2, 'params_googlegroup_deletemember', $params_googlegroup_deletemember);
        
        // We gebruiken @ om eventuele PHP warnings te onderdrukken
        $result = @civicrm_api3('Googlegroups', 'deletemember', $params_googlegroup_deletemember);
        
        wachthond($extdebug, 3, 'result_googlegroup_deletemember', $result);
    } 
    catch (\Throwable $e) {
        // Gevangen! Zowel Exceptions als Fatale Errors (zoals TypeError)
        $msg = $e->getMessage();
        wachthond($extdebug, 1, "GOOGLE API FOUT (Delete)", $msg);
    }
}

/**
 * ============================================================================
 * HELPER: MATRIX (MET NIEUWE KEYS)
 * ============================================================================
 */
function acl_helper_get_takenrollen_matrix($contact_id) {

    $huidig_jaar = date('Y');
    
    // Initialiseer de matrix met exact dezelfde keys als base_eid2event
    $takenrollen_matrix = [
        'event_hldn1_id'     => 0, 
        'event_hldn2_id'     => 0, 
        'event_hldn3_id'     => 0,
        'event_kern1_id'     => 0, 
        'event_kern2_id'     => 0, 
        'event_kern3_id'     => 0, 
        'event_keuken0_id'   => 0, 
        'event_keuken1_id'   => 0, 
        'event_keuken2_id'   => 0, 
        'event_keuken3_id'   => 0, 
        'event_gedrag0_id'   => 0, 
        'event_gedrag1_id'   => 0, 
        'event_gedrag2_id'   => 0, 
        'event_boekje0_id'   => 0, 
        'event_boekje1_id'   => 0, 
        'event_boekje2_id'   => 0,
    ];

    // Haal evenementen van dit jaar op met de API4 namen (Taken_rollen.Veldnaam)
    $evenementen = civicrm_api4('Event', 'get', [
        'checkPermissions' => FALSE,
        'select' => [
            'Taken_rollen.hoofdleiding_1',
            'Taken_rollen.hoofdleiding_2',
            'Taken_rollen.hoofdleiding_3',
            'Taken_rollen.kernteam_1',
            'Taken_rollen.kernteam_2',
            'Taken_rollen.kernteam_3', 
            'Taken_rollen.hoofd_gedrag',
            'Taken_rollen.gedrag_team_1',
            'Taken_rollen.gedrag_team_2',
            'Taken_rollen.hoofd_boekje',
            'Taken_rollen.boekje_team_1',
            'Taken_rollen.boekje_team_2',
            'Taken_rollen.hoofd_keuken',
            'Taken_rollen.hoofd_keuken_1',
            'Taken_rollen.hoofd_keuken_2',
            'Taken_rollen.hoofd_keuken_3',
        ],
        'where' => [
            ['start_date', '>=', "$huidig_jaar-01-01"], 
            ['is_active', '=', 1]
        ]
    ]);

    foreach ($evenementen as $evenement) {
        if (($evenement['Taken_rollen.hoofdleiding_1']  ?? 0) == $contact_id)   $takenrollen_matrix['event_hldn1_id']   = $contact_id;
        if (($evenement['Taken_rollen.hoofdleiding_2']  ?? 0) == $contact_id)   $takenrollen_matrix['event_hldn2_id']   = $contact_id;
        if (($evenement['Taken_rollen.hoofdleiding_3']  ?? 0) == $contact_id)   $takenrollen_matrix['event_hldn3_id']   = $contact_id;
        
        if (($evenement['Taken_rollen.kernteam_1']      ?? 0) == $contact_id)   $takenrollen_matrix['event_kern1_id']   = $contact_id;
        if (($evenement['Taken_rollen.kernteam_2']      ?? 0) == $contact_id)   $takenrollen_matrix['event_kern2_id']   = $contact_id;
        if (($evenement['Taken_rollen.kernteam_3']      ?? 0) == $contact_id)   $takenrollen_matrix['event_kern3_id']   = $contact_id;

        if (($evenement['Taken_rollen.hoofd_gedrag']    ?? 0) == $contact_id)   $takenrollen_matrix['event_gedrag0_id'] = $contact_id;
        if (($evenement['Taken_rollen.gedrag_team_1']   ?? 0) == $contact_id)   $takenrollen_matrix['event_gedrag1_id'] = $contact_id;
        if (($evenement['Taken_rollen.gedrag_team_2']   ?? 0) == $contact_id)   $takenrollen_matrix['event_gedrag2_id'] = $contact_id;
        
        if (($evenement['Taken_rollen.hoofd_boekje']    ?? 0) == $contact_id)   $takenrollen_matrix['event_boekje0_id'] = $contact_id;
        if (($evenement['Taken_rollen.boekje_team_1']   ?? 0) == $contact_id)   $takenrollen_matrix['event_boekje1_id'] = $contact_id;
        if (($evenement['Taken_rollen.boekje_team_2']   ?? 0) == $contact_id)   $takenrollen_matrix['event_boekje2_id'] = $contact_id;
        
        if (($evenement['Taken_rollen.hoofd_keuken']    ?? 0) == $contact_id)   $takenrollen_matrix['event_keuken0_id'] = $contact_id;
        if (($evenement['Taken_rollen.hoofd_keuken_1']  ?? 0) == $contact_id)   $takenrollen_matrix['event_keuken1_id'] = $contact_id;
        if (($evenement['Taken_rollen.hoofd_keuken_2']  ?? 0) == $contact_id)   $takenrollen_matrix['event_keuken2_id'] = $contact_id;
        if (($evenement['Taken_rollen.hoofd_keuken_3']  ?? 0) == $contact_id)   $takenrollen_matrix['event_keuken3_id'] = $contact_id;

        if (($evenement['Taken_rollen.hoofd_ehbo']      ?? 0) == $contact_id)   $takenrollen_matrix['event_ehbo0_id']   = $contact_id;
        if (($evenement['Taken_rollen.ehbo_team_1']     ?? 0) == $contact_id)   $takenrollen_matrix['event_ehbo1_id']   = $contact_id;
        if (($evenement['Taken_rollen.ehbo_team_2']     ?? 0) == $contact_id)   $takenrollen_matrix['event_ehbo2_id']   = $contact_id;
        if (($evenement['Taken_rollen.ehbo_team_3']     ?? 0) == $contact_id)   $takenrollen_matrix['event_ehbo3_id']   = $contact_id;
    }
    
    return $takenrollen_matrix;
}