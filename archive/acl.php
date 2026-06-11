<?php

require_once 'acl.civix.php';

use CRM_Acl_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function acl_civicrm_config(&$config): void {
  _acl_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function acl_civicrm_install(): void {
  _acl_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function acl_civicrm_enable(): void {
  _acl_civix_civicrm_enable();
}

/*
function acl_civicrm_custom($op, $groupID, $entityID) {

    return;

}
*/

function acl_civicrm_configure($array_contditjaar, $ditjaar_array, $allpart_array, $drupal_id = NULL, $eventrollen_array = NULL) {

    $extdebug                   = 3;          // 1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug                   = FALSE;
    $extwrite                   = 1;
    $regpast                    = 1;

    $today_datetime             = date("Y-m-d H:i:s");

    $contact_id                 = $array_contditjaar['contact_id']              ?? NULL;
    $displayname                = $array_contditjaar['displayname']             ?? NULL;
    $laatste_keer               = $array_contditjaar['laatstekeer']             ?? NULL;    // M61: tbv berekenen jaar 'mee komend jaar'       
    $curcv_keer_deel            = $array_contditjaar['curcv_keer_deel']         ?? NULL;
    $curcv_keer_leid            = $array_contditjaar['curcv_keer_leid']         ?? NULL;
    $datum_belangstelling       = $array_contditjaar['datum_belangstelling']    ?? NULL;

    $eventrollen                = $eventrollen_array;

    $ditjaar_pos_kampfunctie    = $allpart_array['result_allpart_pos_kampfunctie'];
    $ditjaar_pos_kampkort       = $allpart_array['result_allpart_pos_kampkort'];    

    if ($contact_id > 0 AND $ditjaar_array) {

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "### ACL - START ACL CHECK VOOR $displayname $ditjaar_pos_kampfunctie $ditjaar_pos_kampkort", "[cid: $contact_id]");
        wachthond($extdebug,1, "########################################################################");     

    } else {
        wachthond($extdebug,4, "civicrm_custom",    $groupID);
        return; // if not, get out of here
    }    

    wachthond($extdebug,4, "drupal_id",             $drupal_id);
    wachthond($extdebug,3, "ditjaar_array",         $ditjaar_array);
    wachthond($extdebug,3, "allpart_array",         $allpart_array);
    wachthond($extdebug,3, "eventrollen_array",     $eventrollen_array);

    wachthond($extdebug,3, "curcv_keer_deel",       $curcv_keer_deel);
    wachthond($extdebug,3, "curcv_keer_leid",       $curcv_keer_leid);
    wachthond($extdebug,3, "datum_belangstelling",  $datum_belangstelling);

    $ditjaardeelyes         = $ditjaar_array['ditjaardeelyes']; 
    $ditjaardeelnot         = $ditjaar_array['ditjaardeelnot'];
    $ditjaardeelmss         = $ditjaar_array['ditjaardeelmss']; 
    $ditjaardeelstf         = $ditjaar_array['ditjaardeelstf']; 
    $ditjaardeeltst         = $ditjaar_array['ditjaardeeltst']; 
    $ditjaardeeltxt         = $ditjaar_array['ditjaardeeltxt']; 

    $ditjaarleidyes         = $ditjaar_array['ditjaarleidyes']; 
    $ditjaarleidnot         = $ditjaar_array['ditjaarleidnot']; 
    $ditjaarleidmss         = $ditjaar_array['ditjaarleidmss']; 
    $ditjaarleidstf         = $ditjaar_array['ditjaarleidstf']; 
    $ditjaarleidtst         = $ditjaar_array['ditjaarleidtst']; 
    $ditjaarleidtxt         = $ditjaar_array['ditjaarleidtxt'];

    wachthond($extdebug,2, "ditjaardeelyes",            $ditjaardeelyes);
    wachthond($extdebug,2, "ditjaardeelnot",            $ditjaardeelnot);
    wachthond($extdebug,2, "ditjaarleidyes",            $ditjaarleidyes);
    wachthond($extdebug,2, "ditjaarleidnot",            $ditjaarleidnot);    

    // M61: TODO beter om hier gewoon query op ingevulde taken/rollen uit event te halen

    $ditjaar_hldn1_id                   = $eventrollen_array['ditjaar_event_hldn1_id'];
    $ditjaar_hldn2_id                   = $eventrollen_array['ditjaar_event_hldn2_id'];
    $ditjaar_hldn3_id                   = $eventrollen_array['ditjaar_event_hldn3_id'];

    $ditjaar_kern1_id                   = $eventrollen_array['ditjaar_event_kern1_id'];
    $ditjaar_kern2_id                   = $eventrollen_array['ditjaar_event_kern2_id'];
    $ditjaar_kern3_id                   = $eventrollen_array['ditjaar_event_kern3_id'];

    $ditjaar_keuken1_id                 = $eventrollen_array['ditjaar_event_keuken1_id'];
    $ditjaar_keuken2_id                 = $eventrollen_array['ditjaar_event_keuken2_id'];
    $ditjaar_keuken3_id                 = $eventrollen_array['ditjaar_event_keuken3_id'];
    $ditjaar_keuken4_id                 = 19210;    // M61: CRM ID van Daniel Fritschij

    $ditjaar_gedrag1_id                 = $eventrollen_array['ditjaar_event_gedrag1_id'];
    $ditjaar_gedrag2_id                 = $eventrollen_array['ditjaar_event_gedrag2_id'];
    $ditjaar_gedrag3_id                 = $eventrollen_array['ditjaar_event_gedrag3_id'];

    $ditjaar_boekje1_id                 = $eventrollen_array['ditjaar_event_boekje1_id'];
    $ditjaar_boekje2_id                 = $eventrollen_array['ditjaar_event_boekje2_id'];
    $ditjaar_boekje3_id                 = $eventrollen_array['ditjaar_event_boekje3_id'];

    $ditjaar_pos_part_id                = $allpart_array['result_allpart_pos_part_id'];
    $ditjaar_pos_deel_part_id           = $allpart_array['result_allpart_pos_deel_part_id'];
    $ditjaar_pos_leid_part_id           = $allpart_array['result_allpart_pos_leid_part_id'];

    $ditjaar_pos_event_id               = $allpart_array['result_allpart_pos_event_id'];
    $ditjaar_pos_deel_event_id          = $allpart_array['result_allpart_pos_deel_event_id'];
    $ditjaar_pos_leid_event_id          = $allpart_array['result_allpart_pos_leid_event_id'];

    $ditjaar_pos_event_type_id          = $allpart_array['result_allpart_pos_event_type_id'];
    $ditjaar_pos_deel_event_type_id     = $allpart_array['result_allpart_pos_deel_event_type_id'];
    $ditjaar_pos_leid_event_type_id     = $allpart_array['result_allpart_pos_leid_event_type_id'];

    $ditjaar_pos_part_status_id         = $allpart_array['result_allpart_pos_part_status_id'];
    $ditjaar_pos_deel_part_status_id    = $allpart_array['result_allpart_pos_deel_part_status_id'];
    $ditjaar_pos_leid_part_status_id    = $allpart_array['result_allpart_pos_leid_part_status_id'];

    $ditjaar_pos_kampfunctie            = $allpart_array['result_allpart_pos_kampfunctie'];
    $ditjaar_pos_deel_kampfunctie       = $allpart_array['result_allpart_pos_deel_kampfunctie'];
    $ditjaar_pos_leid_kampfunctie       = $allpart_array['result_allpart_pos_leid_kampfunctie'];

    $ditjaar_pos_kampkort               = $allpart_array['result_allpart_pos_kampkort'];
    $ditjaar_pos_deel_kampkort          = $allpart_array['result_allpart_pos_deel_kampkort'];
    $ditjaar_pos_leid_kampkort          = $allpart_array['result_allpart_pos_leid_kampkort'];

    wachthond($extdebug,2, "ditjaar_pos_kampfunctie",   $ditjaar_pos_kampfunctie);
    wachthond($extdebug,2, "ditjaar_pos_kampkort",      $ditjaar_pos_kampkort);

    $array_cv = cv_civicrm_configure($contact_id, $array_contditjaar, $ditjaar_array);
    wachthond($extdebug,3,  'RECEIVE array_cv', $array_cv);

    if (is_array($array_cv)) {

        $keren_deel         = $array_cv['keren_deel']           ?? NULL;
        $keren_leid         = $array_cv['keren_leid']           ?? NULL;
        $keren_top          = $array_cv['keren_top']            ?? NULL;
        $totaal_mee         = $array_cv['totaal_mee']           ?? NULL;

        $cv_deel            = $array_cv['cv_deel']              ?? NULL;
        $cv_leid            = $array_cv['cv_leid']              ?? NULL;

        $evtcv_deel         = $array_cv['evtcv_deel']           ?? NULL;
        $evtcv_leid         = $array_cv['evtcv_leid']           ?? NULL;

        $evtcv_deel_nr      = $array_cv['evtcv_deel_nr']        ?? NULL;
        $evtcv_leid_nr      = $array_cv['evtcv_leid_nr']        ?? NULL;

        $evtcv_toer_array   = $array_cv['evtcv_toer_array']     ?? NULL;
        $evtcv_meet_array   = $array_cv['evtcv_meet_array']     ?? NULL;
    }

    $aclgroup_gedrag     = NULL;

    $aclgroup_gedrag_kk1 = 1766;
    $aclgroup_gedrag_kk2 = 1767;
    $aclgroup_gedrag_bk1 = 1768;
    $aclgroup_gedrag_bk2 = 1769;
    $aclgroup_gedrag_tk1 = 1770;
    $aclgroup_gedrag_tk2 = 1771;
    $aclgroup_gedrag_jk1 = 1772;
    $aclgroup_gedrag_jk2 = 1773;
    $aclgroup_gedrag_top = 1774;

    $aclgroup_gedrag_array = array(
        $aclgroup_gedrag_kk1,  $aclgroup_gedrag_kk2,
        $aclgroup_gedrag_bk1,  $aclgroup_gedrag_bk2, 
        $aclgroup_gedrag_tk1,  $aclgroup_gedrag_tk2,
        $aclgroup_gedrag_jk1,  $aclgroup_gedrag_jk2,
        $aclgroup_gedrag_top
    );

    if ($ditjaar_pos_leid_kampkort == 'kk1')   { $aclgroup_gedrag = $aclgroup_gedrag_kk1; }
    if ($ditjaar_pos_leid_kampkort == 'kk2')   { $aclgroup_gedrag = $aclgroup_gedrag_kk2; }
    if ($ditjaar_pos_leid_kampkort == 'bk1')   { $aclgroup_gedrag = $aclgroup_gedrag_bk1; }
    if ($ditjaar_pos_leid_kampkort == 'bk2')   { $aclgroup_gedrag = $aclgroup_gedrag_bk2; }
    if ($ditjaar_pos_leid_kampkort == 'tk1')   { $aclgroup_gedrag = $aclgroup_gedrag_tk1; }
    if ($ditjaar_pos_leid_kampkort == 'tk2')   { $aclgroup_gedrag = $aclgroup_gedrag_tk2; }
    if ($ditjaar_pos_leid_kampkort == 'jk1')   { $aclgroup_gedrag = $aclgroup_gedrag_jk1; }
    if ($ditjaar_pos_leid_kampkort == 'jk2')   { $aclgroup_gedrag = $aclgroup_gedrag_jk2; }
    if ($ditjaar_pos_leid_kampkort == 'top')   { $aclgroup_gedrag = $aclgroup_gedrag_top; }

    $aclgroup_drukwerk_kk1 = 1666;
    $aclgroup_drukwerk_kk2 = 1667;
    $aclgroup_drukwerk_bk1 = 1668;
    $aclgroup_drukwerk_bk2 = 1669;
    $aclgroup_drukwerk_tk1 = 1670;
    $aclgroup_drukwerk_tk2 = 1671;
    $aclgroup_drukwerk_jk1 = 1672;
    $aclgroup_drukwerk_jk2 = 1673;
    $aclgroup_drukwerk_top = 1674;

    $aclgroup_drukwerk_array = array(
        $aclgroup_drukwerk_kk1, $aclgroup_drukwerk_kk2,
        $aclgroup_drukwerk_bk1, $aclgroup_drukwerk_bk2, 
        $aclgroup_drukwerk_tk1, $aclgroup_drukwerk_tk2,
        $aclgroup_drukwerk_jk1, $aclgroup_drukwerk_jk2,
        $aclgroup_drukwerk_top
    );

    if ($ditjaar_pos_leid_kampkort == 'kk1')   { $aclgroup_drukwerk = $aclgroup_drukwerk_kk1; }
    if ($ditjaar_pos_leid_kampkort == 'kk2')   { $aclgroup_drukwerk = $aclgroup_drukwerk_kk2; }
    if ($ditjaar_pos_leid_kampkort == 'bk1')   { $aclgroup_drukwerk = $aclgroup_drukwerk_bk1; }
    if ($ditjaar_pos_leid_kampkort == 'bk2')   { $aclgroup_drukwerk = $aclgroup_drukwerk_bk2; }
    if ($ditjaar_pos_leid_kampkort == 'tk1')   { $aclgroup_drukwerk = $aclgroup_drukwerk_tk1; }
    if ($ditjaar_pos_leid_kampkort == 'tk2')   { $aclgroup_drukwerk = $aclgroup_drukwerk_tk2; }
    if ($ditjaar_pos_leid_kampkort == 'jk1')   { $aclgroup_drukwerk = $aclgroup_drukwerk_jk1; }
    if ($ditjaar_pos_leid_kampkort == 'jk2')   { $aclgroup_drukwerk = $aclgroup_drukwerk_jk2; }
    if ($ditjaar_pos_leid_kampkort == 'top')   { $aclgroup_drukwerk = $aclgroup_drukwerk_top; }

    $aclgroup_keukenchef_kk1 = 1675;
    $aclgroup_keukenchef_kk2 = 1676;
    $aclgroup_keukenchef_bk1 = 1677;
    $aclgroup_keukenchef_bk2 = 1678;
    $aclgroup_keukenchef_tk1 = 1679;
    $aclgroup_keukenchef_tk2 = 1680;
    $aclgroup_keukenchef_jk1 = 1681;
    $aclgroup_keukenchef_jk2 = 1682;
    $aclgroup_keukenchef_top = 1683;

    $aclgroup_keukenchef_array = array(
        $aclgroup_keukenchef_kk1, $aclgroup_keukenchef_kk2,
        $aclgroup_keukenchef_bk1, $aclgroup_keukenchef_bk2, 
        $aclgroup_keukenchef_tk1, $aclgroup_keukenchef_tk2,
        $aclgroup_keukenchef_jk1, $aclgroup_keukenchef_jk2,
        $aclgroup_keukenchef_top
    );

    if ($ditjaar_pos_leid_kampkort == 'kk1')   { $aclgroup_keukenchef = $aclgroup_keukenchef_kk1; }
    if ($ditjaar_pos_leid_kampkort == 'kk2')   { $aclgroup_keukenchef = $aclgroup_keukenchef_kk2; }
    if ($ditjaar_pos_leid_kampkort == 'bk1')   { $aclgroup_keukenchef = $aclgroup_keukenchef_bk1; }
    if ($ditjaar_pos_leid_kampkort == 'bk2')   { $aclgroup_keukenchef = $aclgroup_keukenchef_bk2; }
    if ($ditjaar_pos_leid_kampkort == 'tk1')   { $aclgroup_keukenchef = $aclgroup_keukenchef_tk1; }
    if ($ditjaar_pos_leid_kampkort == 'tk2')   { $aclgroup_keukenchef = $aclgroup_keukenchef_tk2; }
    if ($ditjaar_pos_leid_kampkort == 'jk1')   { $aclgroup_keukenchef = $aclgroup_keukenchef_jk1; }
    if ($ditjaar_pos_leid_kampkort == 'jk2')   { $aclgroup_keukenchef = $aclgroup_keukenchef_jk2; }
    if ($ditjaar_pos_leid_kampkort == 'top')   { $aclgroup_keukenchef = $aclgroup_keukenchef_top; }

    $aclgroup_keukenteam_kk1 = 1871;
    $aclgroup_keukenteam_kk2 = 1872;
    $aclgroup_keukenteam_bk1 = 1873;
    $aclgroup_keukenteam_bk2 = 1874;
    $aclgroup_keukenteam_tk1 = 1875;
    $aclgroup_keukenteam_tk2 = 1876;
    $aclgroup_keukenteam_jk1 = 1877;
    $aclgroup_keukenteam_jk2 = 1878;
    $aclgroup_keukenteam_top = 1879;

    $aclgroup_keukenteam_array = array(
        $aclgroup_keukenteam_kk1, $aclgroup_keukenteam_kk2,
        $aclgroup_keukenteam_bk1, $aclgroup_keukenteam_bk2,
        $aclgroup_keukenteam_tk1, $aclgroup_keukenteam_tk2,
        $aclgroup_keukenteam_jk1, $aclgroup_keukenteam_jk2,
        $aclgroup_keukenteam_top
    );

    if ($ditjaar_pos_leid_kampkort == 'kk1')   { $aclgroup_keukenteam = $aclgroup_keukenteam_kk1; }
    if ($ditjaar_pos_leid_kampkort == 'kk2')   { $aclgroup_keukenteam = $aclgroup_keukenteam_kk2; }
    if ($ditjaar_pos_leid_kampkort == 'bk1')   { $aclgroup_keukenteam = $aclgroup_keukenteam_bk1; }
    if ($ditjaar_pos_leid_kampkort == 'bk2')   { $aclgroup_keukenteam = $aclgroup_keukenteam_bk2; }
    if ($ditjaar_pos_leid_kampkort == 'tk1')   { $aclgroup_keukenteam = $aclgroup_keukenteam_tk1; }
    if ($ditjaar_pos_leid_kampkort == 'tk2')   { $aclgroup_keukenteam = $aclgroup_keukenteam_tk2; }
    if ($ditjaar_pos_leid_kampkort == 'jk1')   { $aclgroup_keukenteam = $aclgroup_keukenteam_jk1; }
    if ($ditjaar_pos_leid_kampkort == 'jk2')   { $aclgroup_keukenteam = $aclgroup_keukenteam_jk2; }
    if ($ditjaar_pos_leid_kampkort == 'top')   { $aclgroup_keukenteam = $aclgroup_keukenteam_top; }

    $aclgroup_kamp_kk1   = 1853;
    $aclgroup_kamp_kk2   = 1854;
    $aclgroup_kamp_bk1   = 1855;
    $aclgroup_kamp_bk2   = 1856;
    $aclgroup_kamp_tk1   = 1857;
    $aclgroup_kamp_tk2   = 1858;
    $aclgroup_kamp_jk1   = 1859;
    $aclgroup_kamp_jk2   = 1860;
    $aclgroup_kamp_top   = 1861;

    $aclgroup_kamp_array = array(
        $aclgroup_kamp_kk1, $aclgroup_kamp_kk2,
        $aclgroup_kamp_bk1, $aclgroup_kamp_bk2, 
        $aclgroup_kamp_tk1, $aclgroup_kamp_tk2,
        $aclgroup_kamp_jk1, $aclgroup_kamp_jk2,
        $aclgroup_kamp_top
    );

    if ($ditjaar_pos_leid_kampkort == 'kk1')   { $aclgroup_kamp = $aclgroup_kamp_kk1; }
    if ($ditjaar_pos_leid_kampkort == 'kk2')   { $aclgroup_kamp = $aclgroup_kamp_kk2; }
    if ($ditjaar_pos_leid_kampkort == 'bk1')   { $aclgroup_kamp = $aclgroup_kamp_bk1; }
    if ($ditjaar_pos_leid_kampkort == 'bk2')   { $aclgroup_kamp = $aclgroup_kamp_bk2; }
    if ($ditjaar_pos_leid_kampkort == 'tk1')   { $aclgroup_kamp = $aclgroup_kamp_tk1; }
    if ($ditjaar_pos_leid_kampkort == 'tk2')   { $aclgroup_kamp = $aclgroup_kamp_tk2; }
    if ($ditjaar_pos_leid_kampkort == 'jk1')   { $aclgroup_kamp = $aclgroup_kamp_jk1; }
    if ($ditjaar_pos_leid_kampkort == 'jk2')   { $aclgroup_kamp = $aclgroup_kamp_jk2; }
    if ($ditjaar_pos_leid_kampkort == 'top')   { $aclgroup_kamp = $aclgroup_kamp_top; }

    $aclgroup_topkamp_array          = array(1756);

    $aclgroup_keukenstaf_array       = array(1882); // ALGEMENE ACL GROEP VOOR ALLE KEUKENTEAMLEDEN
    $aclgroup_keukenstaf             = 1882;

    $aclgroup_ditjaardeel_array      = array(1846);
    $aclgroup_alleleiding_array      = array(1849);

    $aclgroup_kampstaf_array         = array(456);
    $aclgroup_kernteam_array         = array(1842);
    $aclgroup_hoofd_array            = array(1976);

    ### GOOGLEGROUPS NOTIF DEEL

    $googlegroup_notifdeel_kk1 = '01baon6m3wo0451';
    $googlegroup_notifdeel_kk2 = '00vx12273fgfnd5';
    $googlegroup_notifdeel_bk1 = '00lnxbz9161bbzw';
    $googlegroup_notifdeel_bk2 = '0147n2zr2s87rx7';
    $googlegroup_notifdeel_tk1 = '02xcytpi1fs7xwo';
    $googlegroup_notifdeel_tk2 = '01opuj5n2028q4s';
    $googlegroup_notifdeel_jk1 = '02bn6wsx3827ior';
    $googlegroup_notifdeel_jk2 = '030j0zll0m5pg5h';
    $googlegroup_notifdeel_top = '00haapch3zvbjru';

    $googlegroup_notifdeel_array = array(
        $googlegroup_notifdeel_kk1, $googlegroup_notifdeel_kk2,
        $googlegroup_notifdeel_bk1, $googlegroup_notifdeel_bk2,
        $googlegroup_notifdeel_tk1, $googlegroup_notifdeel_tk2,
        $googlegroup_notifdeel_jk1, $googlegroup_notifdeel_jk2,
        $googlegroup_notifdeel_top
    );

    if ($ditjaar_pos_leid_kampkort == 'kk1')   { $googlegroup_notifdeel = $googlegroup_notifdeel_kk1; }
    if ($ditjaar_pos_leid_kampkort == 'kk2')   { $googlegroup_notifdeel = $googlegroup_notifdeel_kk2; }
    if ($ditjaar_pos_leid_kampkort == 'bk1')   { $googlegroup_notifdeel = $googlegroup_notifdeel_bk1; }
    if ($ditjaar_pos_leid_kampkort == 'bk2')   { $googlegroup_notifdeel = $googlegroup_notifdeel_bk2; }
    if ($ditjaar_pos_leid_kampkort == 'tk1')   { $googlegroup_notifdeel = $googlegroup_notifdeel_tk1; }
    if ($ditjaar_pos_leid_kampkort == 'tk2')   { $googlegroup_notifdeel = $googlegroup_notifdeel_tk2; }
    if ($ditjaar_pos_leid_kampkort == 'jk1')   { $googlegroup_notifdeel = $googlegroup_notifdeel_jk1; }
    if ($ditjaar_pos_leid_kampkort == 'jk2')   { $googlegroup_notifdeel = $googlegroup_notifdeel_jk2; }
    if ($ditjaar_pos_leid_kampkort == 'top')   { $googlegroup_notifdeel = $googlegroup_notifdeel_top; }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 1.0 CHECK IF ACCOUNT IS IN KAMP_ACCOUNTS");
    wachthond($extdebug,2, "########################################################################");

    $aclgroup_kampaccounts  = 2085;
    $aclgroup               = $aclgroup_kampaccounts;
    $rolname                = "kampaccount";

    $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
    $acl_group_label        = $acl_group['group_label'];
    $acl_group_count        = $acl_group['group_count'];

    if ($acl_group_count ==1) {
        $kampaccount        = 1;
    }

    if ($ditjaardeelnot == 1) {

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "### ACL 1.1 NIET MEE ALS DEELNEMER DIT JAAR DUS VERWIJDER ALLE RECHTEN VOOR $displayname");
        wachthond($extdebug,1, "########################################################################");     

        acl_group_remove($contact_id, $aclgroup_topkamp_array,      'topkamp');
        acl_group_remove($contact_id, $aclgroup_ditjaardeel_array,  'ditjaardeel');

        cms_rol_remove($drupal_id, $displayname, 'ditjaar_deelnemer');
    }

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### ACL 1.1 KEN MEMBERSHIP PRESALE TOE AAN DEELNEMER VORIG JAAR: $displayname");
    wachthond($extdebug,1, "########################################################################");     

    // =========================================================================
    // DATUM LOGICA & PERFORMANCE CACHE
    // =========================================================================
    
    // Technisch: Variabele 'static' maken zorgt ervoor dat de datum-berekening 
    // slechts één keer wordt uitgevoerd voor het hele script, ongeacht het aantal contacten.
    // Dit bespaart rekentijd bij grote batches (DateTime objecten zijn relatief zwaar).
    static $augustusDeadline = NULL;

    if ($augustusDeadline === NULL) {
        // Functioneel: De peildatum voor lidmaatschap/presale is 1 augustus.
        // We bepalen hier welk "boekjaar" actueel is.
        $augustusDeadline = new DateTime('first day of August');

        // Functioneel: Als 1 augustus van dit jaar nog in de toekomst ligt (bijv. het is nu mei),
        // dan horen we bij het seizoen dat startte op 1 augustus vorig jaar.
        if ($augustusDeadline > new DateTime()) {
            $augustusDeadline->modify('-1 year');
        }
    }

    // Functioneel: Controleer of het jaar waarin de persoon voor het laatst mee was ($laatste_keer),
    // overeenkomt met het berekende peiljaar. Zo ja, dan is het 'Juiste Jaar' voor korting/presale.
    $isJuisteJaar = ((int)$laatste_keer === (int)$augustusDeadline->format('Y'));

    // 2. Haal bestaande memberships op
    $params_membership_get = [
      'checkPermissions' => FALSE,
      'debug' => $apidebug,
        'select' => [
            'id', 'row_count', 'join_date', 'start_date', 'end_date',
        ],
        'where' => [
            ['contact_id',                  '=', $contact_id],
            ['status_id:label',             '=', 'Actief'],
            ['membership_type_id:label',    '=', 'presalemember'],
        ],
    ];

    wachthond($extdebug,3, 'params_membership_get',                     $params_membership_get);
    $result_membership_get = civicrm_api4('Membership','get',           $params_membership_get);
    wachthond($extdebug,9, 'result_membership_get',                     $result_membership_get);

    $membership_id      = $result_membership_get->first()['id'] ?? NULL;
    $heeftMembership    = (bool)$membership_id;

    // 3. Logica voor acties
    if ($isJuisteJaar && !$heeftMembership) {

        // Actie: Aanmaken (jaar klopt, maar heeft nog niets)
        $params_membership_create = [
            'checkPermissions' => FALSE,
            'debug'  => $apidebug,
            'values' => [
                'contact_id'                => $contact_id,
                'membership_type_id:label'  => 'presalemember',
                'status_id:label'           => 'Actief',
            ],
        ];
        wachthond($extdebug,3, 'params_membership_create',                  $params_membership_create);
        $result_membership_create = civicrm_api4('Membership','create',     $params_membership_create);
        wachthond($extdebug,3, "Membership presale toegekend aan",          $displayname);
        wachthond($extdebug,9, 'result_membership_create',                  $result_membership_create);

    } elseif (!$isJuisteJaar && $heeftMembership) {

        // Actie: Verwijderen (jaar klopt niet meer, maar heeft nog wel een lidmaatschap)
        $params_membership_delete = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'where' => [
                ['id',          '=', $membership_id],
                ['contact_id',  '=', $contact_id],
            ],
        ];
        wachthond($extdebug,3, 'params_membership_delete',                  $params_membership_delete);
        $result_membership_delete = civicrm_api4('Membership','delete',     $params_membership_delete);
        wachthond($extdebug,3, "Membership presale deleted voor",           $displayname);        
        wachthond($extdebug,9, 'result_membership_delete',                  $result_membership_delete);
    }


    if ($ditjaarleidnot == 1 AND $ditjaardeeltst == 0) {

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "### ACL 1.2 NIET MEE ALS LEIDING DIT JAAR DUS VERWIJDER ALLE RECHTEN VOOR $displayname");
        wachthond($extdebug,1, "########################################################################");     

        acl_group_remove($contact_id, $aclgroup_gedrag_array,       'gedrag');
        acl_group_remove($contact_id, $aclgroup_drukwerk_array,     'drukwerk');

        acl_group_remove($contact_id, $aclgroup_keukenchef_array,   'keukenchef');
        acl_group_remove($contact_id, $aclgroup_keukenteam_array,   'keukenteam');
        acl_group_remove($contact_id, $aclgroup_keukenstaf_array,   'keukenstaf');

        acl_group_remove($contact_id, $aclgroup_alleleiding_array,  'kampleiding');
        acl_group_remove($contact_id, $aclgroup_kamp_array,         'groepkamp');
        acl_group_remove($contact_id, $aclgroup_kernteam_array,     'kernteam');
        acl_group_remove($contact_id, $aclgroup_hoofd_array,        'hoofdleiding');

        if ($kampaccount == 0) {
            acl_group_remove($contact_id, $aclgroup_kampstaf_array, 'kampstaf');
        }

        cms_rol_remove($drupal_id, $displayname, 'ditjaar_hoofdleiding');
        cms_rol_remove($drupal_id, $displayname, 'ditjaar_keukenhoofd');
        cms_rol_remove($drupal_id, $displayname, 'ditjaar_keukenteam');
        cms_rol_remove($drupal_id, $displayname, 'ditjaar_kernteam');
        cms_rol_remove($drupal_id, $displayname, 'ditjaar_alleleiding');
        cms_rol_remove($drupal_id, $displayname, 'ditjaar_groepsleiding');

        if ($kampaccount == 0) {
            cms_rol_remove($drupal_id, $displayname, 'ditjaar_kampstaf');
        }

    }

    wachthond($extdebug,2, "########################################################################");     
    wachthond($extdebug,1, "### ACL 2.1 CHECK OF AL IN ACL GROEP OOITDEELNEMER",     "[$displayname]");
    wachthond($extdebug,2, "########################################################################"); 

    $cmsrol     = 'ooit_deelnemer';

    $hasrol_ooitdeelnemer = cms_rol_check($drupal_id, $displayname, $cmsrol);
    wachthond($extdebug,3, "hasrol_ooitdeelnemer", $hasrol_ooitdeelnemer);

    if ($curcv_keer_deel > 0) {

        if ($hasrol_ooitdeelnemer != 1 AND $drupal_id > 0) {
            cms_rol_add($drupal_id, $displayname, $cmsrol);
        }

    } else {

        if ($hasrol_ooitdeelnemer == 1 AND $drupal_id > 0) {
            cms_rol_remove($drupal_id, $displayname, $cmsrol);
        }
    }

    if ($ditjaardeelyes == 1 AND $ditjaar_pos_deel_part_id > 0) {

        wachthond($extdebug,1, "########################################################################");
        wachthond($extdebug,1, "### ACL 2.X HUIDIG JAAR DEEL $displayname", "[$ditjaar_pos_leid_kampfunctie $ditjaar_pos_leid_kampkort]");
        wachthond($extdebug,1, "########################################################################");     

        if ($drupal_id) {
            $rol_cms_deelnemer      = "ditjaar_deelnemer";
            $drupal_role_change     = drupal_role_change($drupal_id, $rol_cms_deelnemer, 'ADD');
            wachthond($extdebug,1, "Drupal rol $rol_cms_deelnemer aangepast", "[ADD]");
        } else {
            wachthond($extdebug,1, "GEEN WAARDE VOOR VALID DRUPALID", $drupal_id);
        }

        if ($ditjaar_pos_deel_kampkort == 'top' AND $ditjaardeelyes == 1) {

            wachthond($extdebug,1, "### ACL 2.1 START SEGMENT PART DIT EVENT & HUIDIG JAAR TOPKAMP", "[$ditjaar_pos_leid_kampfunctie $ditjaar_pos_leid_kampkort]");

            wachthond($extdebug,2, "########################################################################");     
            wachthond($extdebug,1, "### ACL 2.1 a CHECK IF DEELNEMER TOP AL IN DE ACL GROEP ZIT VAN DITJAAR DEEL TOP", "[$displayname $ditjaar_pos_deel_kampfunctie $ditjaar_pos_deel_kampkort]");
            wachthond($extdebug,2, "########################################################################"); 

            if ($ditjaardeeltop == 1) {
                $aclgroup_topkamp = 1756;
            } else {
                $aclgroup_topkamp = NULL;
            }

            $group_topkamp          = acl_group_get($contact_id, $aclgroup_topkamp, 'topkamp');
            $group_topkamp_count    = $group_topkamp['group_count'];
            $group_topkamp_member   = $group_topkamp['group_member'];

            wachthond($extdebug,2, "########################################################################");
            wachthond($extdebug,1, "### ACL 2.1 b VOEG DEELNEMERS TOPKAMP TOE AAN ACL GROEP DITJAAR DEEL TOPKAMP", "[$displayname $ditjaar_pos_deel_kampfunctie $ditjaar_pos_deel_kampkort]");
            wachthond($extdebug,2, "########################################################################");     

            if ($ditjaar_pos_deel_kampkort == 'top' AND $group_topkamp_count == 0) {

                acl_group_create($contact_id, $aclgroup_topkamp, 'topkamp');
            }
            wachthond($extdebug,2, "########################################################################");     
            wachthond($extdebug,1, "### ACL 2.1 c INDIEN (WEER) MEE MET TOPKAMP VOEG DAN OOK TOE AAN ACL GROEP DITJAAR DEEL TOPKAMP", "[$displayname $ditjaar_pos_deel_kampfunctie $ditjaar_pos_deel_kampkort]");
            wachthond($extdebug,2, "########################################################################");     

            if ($ditjaar_pos_deel_kampkort == 'top' AND $group_topkamp_count == 1 AND $group_topkamp_member == 0) {

                acl_group_update($contact_id, $aclgroup_topkamp, 'topkamp');
            }
            wachthond($extdebug,2, "########################################################################");     
            wachthond($extdebug,1, "### ACL 2.1 d INDIEN NIET MEE MET TOPKAMP DIT JAAR", "[$displayname $ditjaar_pos_deel_kampfunctie $ditjaar_pos_deel_kampkort]");
            wachthond($extdebug,2, "########################################################################");     

            if ($ditjaardeelnot == 1 AND $group_topkamp_member == 1) {

                acl_group_remove($contact_id, $aclgroup_topkamp, 'topkamp');
            }   
        }
    }

    wachthond($extdebug,2, "########################################################################");     
    wachthond($extdebug,1, "### ACL 3.1 CHECK OF AL IN ACL GROEP BELANGSTELLING",    "[$displayname]");
    wachthond($extdebug,2, "########################################################################"); 

    wachthond($extdebug,3, "datum_belangstelling",  $datum_belangstelling);
    wachthond($extdebug,3, "drupal_id",             $drupal_id);

    $aclgroup               = 855;
    $cmsrol                 = 'ooit_belangstelling';
    $rolname                = 'belangstelling';

    $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
    $acl_group_label        = $acl_group['group_label'];
    $acl_group_count        = $acl_group['group_count'];
    $acl_group_member       = $acl_group['group_member'];
    $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

    $acl_array = array(
        'aclgroup'          =>  $aclgroup,
        'cmsrol'            =>  $cmsrol,
        'rolname'           =>  $rolname,
        'acl_group_label'   =>  $acl_group_label,
        'acl_group_count'   =>  $acl_group_count,
        'acl_group_member'  =>  $acl_group_member,
        'cms_hasrol'        =>  $cms_hasrol,
    );

    wachthond($extdebug,3, "acl_array", $acl_array);

    if ($datum_belangstelling) {
        permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
    } else {
        permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
    }

    wachthond($extdebug,2, "########################################################################");     
    wachthond($extdebug,1, "### ACL 4.1 CHECK OF AL IN ACL GROEP OOITLEIDING",       "[$displayname]");
    wachthond($extdebug,2, "########################################################################"); 

    $cmsrol     = 'ooit_leiding';

    $hasrol_ooitleiding = cms_rol_check($drupal_id, $displayname, $cmsrol);
    wachthond($extdebug,3, "hasrol_ooitleiding", $hasrol_ooitleiding);

    if ($curcv_keer_leid > 0) {

        if ($hasrol_ooitleiding != 1 AND $drupal_id > 0) {
            cms_rol_add($drupal_id, $displayname, $cmsrol);
        }

    } else {

        if ($hasrol_ooitleiding == 1 AND $drupal_id > 0) {
            cms_rol_remove($drupal_id, $displayname, $cmsrol);
        }
    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");     
        wachthond($extdebug,1, "### ACL 4.2 CHECK OF AL IN ACL GROEP ALLELEIDING",       "[$displayname]");
        wachthond($extdebug,2, "########################################################################"); 

        $aclgroup               = 1849;
        $cmsrol                 = 'ditjaar_alleleiding';
        $rolname                = 'alleleiding';

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array", $acl_array);

        if ($ditjaarleidyes == 1) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");     
        wachthond($extdebug,1, "### ACL 4.3 CHECK OF AL IN ACL GROEP GROEPSLEIDING",     "[$displayname]");
        wachthond($extdebug,2, "########################################################################"); 

        $aclgroup               = 1850;
        $cmsrol                 = 'ditjaar_groepsleiding';
        $rolname                = 'groepsleiding';

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array", $acl_array);

        if ($ditjaarleidyes == 1 AND $ditjaar_pos_leid_kampfunctie == 'groepsleiding') {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }    

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.4 CHECK IF USER IS DEEL VAN ACL GROEP GEDRAG", "[$displayname]");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup               = $aclgroup_gedrag;
        $cmsrol                 = "ditjaar_teamspecials";
        $rolname                = "teamspecials";

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array", $acl_array);

        wachthond($extdebug,3, "ditjaar_gedrag1_id",    $ditjaar_gedrag1_id);
        wachthond($extdebug,3, "ditjaar_gedrag2_id",    $ditjaar_gedrag2_id);
        wachthond($extdebug,3, "ditjaar_gedrag3_id",    $ditjaar_gedrag3_id);

        if (in_array($contact_id, array($ditjaar_gedrag1_id, $ditjaar_gedrag2_id, $ditjaar_gedrag3_id))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.5 CHECK IF USER IS DEEL VAN ACL GROEP DRUKWERK");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup               = $aclgroup_drukwerk;
        $cmsrol                 = 'ditjaar_drukwerk';
        $rolname                = "drukwerk";

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array",                 $acl_array);

        wachthond($extdebug,3, "ditjaar_boekje1_id",        $ditjaar_boekje1_id);
        wachthond($extdebug,3, "ditjaar_boekje2_id",        $ditjaar_boekje2_id);
        wachthond($extdebug,3, "ditjaar_boekje3_id",        $ditjaar_boekje3_id);

        if (in_array($contact_id, array($ditjaar_boekje1_id, $ditjaar_boekje2_id, $ditjaar_boekje3_id))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }    

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.6 a CHECK IF USER IS DEEL VAN ACL GROEP KEUKENHOOFD");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup               = $aclgroup_keukenchef;
        $cmsrol                 = "ditjaar_keukenhoofd";
        $rolname                = "keukenhoofd";

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array",             $acl_array);

        wachthond($extdebug,3, "ditjaar_keuken1_id",    $ditjaar_keuken1_id);
        wachthond($extdebug,3, "ditjaar_keuken2_id",    $ditjaar_keuken2_id);
        wachthond($extdebug,3, "ditjaar_keuken3_id",    $ditjaar_keuken3_id);
        wachthond($extdebug,3, "ditjaar_keuken4_id",    $ditjaar_keuken4_id);

        if ($ditjaar_pos_leid_kampfunctie == 'hoofdkeuken' AND in_array($contact_id, array($ditjaar_keuken1_id, $ditjaar_keuken2_id, $ditjaar_keuken3_id, $ditjaar_keuken4_id))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    wachthond($extdebug,2, "########################################################################");
    wachthond($extdebug,1, "### ACL 4.6 b VOEG DANIEL FRITSCHIJ TOE AAN ALLE KEUKENCHEF GROEPEN");
    wachthond($extdebug,2, "########################################################################");

    if ($contact_id == 19210) { // M61: Daniel Fritschij

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        foreach ($aclgroup_keukenchef_array as $aclgroup) {
            $acl_group                      = acl_group_get($contact_id, $aclgroup, $rolname);
            $acl_array['aclgroup']          = $aclgroup;
            $acl_array['acl_group_label']   = $acl_group['group_label'];
            $acl_array['acl_group_count']   = $acl_group['group_count'];
            $acl_array['acl_group_member']  = $acl_group['group_member'];
            wachthond($extdebug, 3, "ACL_ARRAY", $acl_array);
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.7 CHECK IF USER IS DEEL VAN ACL GROEP KEUKENTEAM $ditjaar_pos_leid_kampkort");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup               = $aclgroup_keukenteam;
        $cmsrol                 = "ditjaar_keukenteam";
        $rolname                = "keukenteam";

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array",             $acl_array);

        if (in_array($ditjaar_pos_leid_kampfunctie, array('hoofdkeuken', 'keukenteamlid'))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 4.8 CHECK IF USER IS DEEL VAN ACL GROEP KEUKENTEAM ALGEMEEN");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup               = $aclgroup_keukenstaf;
        $cmsrol                 = "ditjaar_keukenteam";
        $rolname                = "keukenteam";

        $acl_group              = acl_group_get($contact_id, $aclgroup, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array",             $acl_array);

        if (in_array($ditjaar_pos_leid_kampfunctie, array('hoofdkeuken', 'keukenteamlid'))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### ACL 4.X EINDE SEGMENT LEIDING HUIDIG JAAR",    "[$ditjaar_pos_leid_kampfunctie $ditjaar_pos_leid_kampkort]");
    wachthond($extdebug,1, "########################################################################");

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### ACL 5.X SEGMENT BESTUUR/HOOFDLEIDING/KAMPSTAF",      "[$displayname]");
    wachthond($extdebug,1, "########################################################################");

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### ACL 5.1 a CHECK OP GROUP BESTUUR ACL", "[$ditjaar_pos_leid_kampfunctie $ditjaar_pos_leid_kampkort]");
    wachthond($extdebug,1, "########################################################################");     

    $aclgroup_bestuur       = 455;  // M61: hardcoded id of (manual) ACL group ditjaar_bestuur
    $group_bestuur          = 0;

    $group_bestuur          = acl_group_get($contact_id, $aclgroup_bestuur, 'bestuur');
    $group_bestuur_label    = $group_bestuur['group_label'];
    $group_bestuur_count    = $group_bestuur['group_count'];
    $group_bestuur_member   = $group_bestuur['group_member'];

    if ($group_bestuur_member == 1) {

        $ditjaarleidstf     = 1;
        if ($ditjaarleidyes == 1) {
            $ditjaarleidstf = 1;
        }
    }

    if (in_array($ditjaar_pos_leid_kampfunctie, array('hoofdleiding','kernteamlid','bestuurslid','kampstaf'))) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 5.X VOEG HL TOE AAN ACLGROEP KAMP", "[$ditjaar_pos_leid_kampfunctie $ditjaar_pos_leid_kampkort]");
        wachthond($extdebug,2, "########################################################################");

        // M61: INDIEN HANDMATIG TOEGEVOEGD AAN KAMPSTAF ACL

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 5.1 STAF & LEIDING DITKAMP? DAN OOK IN ACL_DITKAMP", "[CHECK STAF]");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup_kampstaf      = 456;  // M61: hardcoded id of (manual) ACL group DITJAAR KAMPSTAF ACL

        $group_kampstaf         = acl_group_get($contact_id, $aclgroup_kampstaf, 'kampstaf');
        $group_staf_label       = $group_kampstaf['group_label'];
        $group_staf_count       = $group_kampstaf['group_count'];
        $group_staf_member      = $group_kampstaf['group_member'];

        if ($group_staf_member == 1) {
            wachthond($extdebug,1, "MEMBER VAN DE GROEP $group_staf_label");
        }

        if ($ditjaar_pos_leid_kampkort == 'bestuurstaken')  {   $aclgroup_kamp = 1862;}
    //  if ($ditjaar_pos_leid_kampkort == 'kampstaf')       {   $aclgroup_kamp = 1862;}

        wachthond($extdebug,4, 'ditjaar_pos_leid_kampkort',     $ditjaar_pos_leid_kampkort);
        wachthond($extdebug,4, 'aclgroupkamp',                  $aclgroup_kamp);

        $group_kamp             = acl_group_get($contact_id,    $aclgroup_kamp, 'kamp');
        $group_kamp_label       = $group_kamp['group_label'];
        $group_kamp_count       = $group_kamp['group_count'];
        $group_kamp_member      = $group_kamp['group_member'];

        if ($group_kamp_member == 1) {
            $group_kamp = 1;
        } else {
            $group_kamp = 0;
        }

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 5.2 a STAF & LEIDING DITKAMP? DAN OOK IN $group_kamp_label", "[CHECK]");
        wachthond($extdebug,2, "########################################################################");

        if (in_array($ditjaar_pos_leid_kampfunctie, array('hoofdleiding','kernteamlid','bestuurslid','kampstaf'))) {

            if ($group_staf_member == 1 AND $group_kamp_count == 0) {

                wachthond($extdebug,2, "########################################################################");
                wachthond($extdebug,1, "### ACL 5.2 b STAF & LEIDING DITKAMP? DAN OOK IN $group_kamp_label", "[CREATE]");
                wachthond($extdebug,2, "########################################################################");

                acl_group_create($contact_id, $aclgroup_kamp, "ditkamp");
            }
            if ($group_staf_member == 1 AND $group_kamp_count == 1 AND $group_kamp_member == 0) {

                wachthond($extdebug,2, "########################################################################");
                wachthond($extdebug,1, "### ACL 5.2 c STAF & LEIDING DITKAMP? DAN OOK IN $group_kamp_label", "[UPDATE]");
                wachthond($extdebug,2, "########################################################################");

                acl_group_update($contact_id, $aclgroup_kamp, "ditkamp");
            }
        }

        if ($group_staf_member == 0 AND $group_kamp_member == 1) {

            wachthond($extdebug,2, "########################################################################");
            wachthond($extdebug,1, "### ACL 5.2 d GEEN STAF & LEIDING DITKAMP? DAN WEG UIT $group_kamp_label", "[VERWIJDER]");
            wachthond($extdebug,2, "########################################################################");

            acl_group_remove($contact_id, $aclgroup_kamp, "ditkamp");
        }

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 5.3 VOEG HL TOE AAN GOOGLEGROEP KAMPSTAF", "[$ditjaar_pos_leid_kampfunctie $ditjaar_pos_leid_kampkort]");
        wachthond($extdebug,2, "########################################################################");

        if ($group_staf_member == 1 AND $group_kamp_member == 1 AND $ditjaarleidyes == 1 AND in_array($ditjaar_pos_leid_kampfunctie, array('hoofdleiding','kernteamlid','bestuurslid','kampstaf'))) {

            $googlegroup_id         = "0147n2zr43f2y48"; // KAMPSTAF ACL
            $googlegroup_batch      = array(
                'Richard'           => "richard@m61.nl",
            );

            $params_googlegroup_subscribe = [
                'group_id'      => $googlegroup_id,
                'emails'        => $googlegroup_batch,
                'role'          => 'MEMBER',
            ];
            try{
                wachthond($extdebug,2, 'params_googlegroup_subscribe',                      $params_googlegroup_subscribe);
//              $result_googlegroup_subscribe = civicrm_api3('Googlegroups','subscribe',    $params_googlegroup_subscribe);
                wachthond($extdebug,2, 'result_googlegroup_subscribe',                      $result_googlegroup_subscribe);
            }
            catch (CiviCRM_API3_Exception $e) {
                // Handle error here.
                $errorMessage = $e->getMessage();
                $errorCode    = $e->getErrorCode();
                $errorData    = $e->getExtraParams();
                wachthond($extdebug,4, "ERRORCODE: $errorCode", $errorMessage);
            }
        }
    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 5.4 CHECK IF USER IS DEEL VAN ACL GROEP HOOFDLEIDING");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup_hoofd         = 1976;
        $cmsrol                 = "ditjaar_hoofdleiding";
        $rolname                = "hoofdleiding";

        $acl_group              = acl_group_get($contact_id, $aclgroup_hoofd, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,            
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array",             $acl_array);

        wachthond($extdebug,3, "ditjaar_hldn1_id",      $ditjaar_hldn1_id);
        wachthond($extdebug,3, "ditjaar_hldn2_id",      $ditjaar_hldn2_id);
        wachthond($extdebug,3, "ditjaar_hldn3_id",      $ditjaar_hldn3_id);

        if ($group_staf_member == 1 AND in_array($contact_id, array($ditjaar_hldn1_id, $ditjaar_hldn2_id, $ditjaar_hldn3_id))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }

    }

    if ($curcv_keer_leid > 0) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 5.5 CHECK IF USER IS DEEL VAN ACL GROEP KERNTEAM");
        wachthond($extdebug,2, "########################################################################");

        $aclgroup_kernteam      = 1842;
        $cmsrol                 = "ditjaar_kernteam";
        $rolname                = "kernteam";

        $acl_group              = acl_group_get($contact_id, $aclgroup_kernteam, $rolname);
        $acl_group_label        = $acl_group['group_label'];
        $acl_group_count        = $acl_group['group_count'];
        $acl_group_member       = $acl_group['group_member'];
        $cms_hasrol             = cms_rol_check($drupal_id, $displayname, $cmsrol);

        $acl_array = array(
            'aclgroup'          =>  $aclgroup,
            'cmsrol'            =>  $cmsrol,
            'rolname'           =>  $rolname,
            'acl_group_label'   =>  $acl_group_label,            
            'acl_group_count'   =>  $acl_group_count,
            'acl_group_member'  =>  $acl_group_member,
            'cms_hasrol'        =>  $cms_hasrol,
        );

        wachthond($extdebug,3, "acl_array",             $acl_array);

        wachthond($extdebug,3, "ditjaar_kern1_id",      $ditjaar_kern1_id);
        wachthond($extdebug,3, "ditjaar_kern2_id",      $ditjaar_kern2_id);
        wachthond($extdebug,3, "ditjaar_kern3_id",      $ditjaar_kern3_id);

        if ($group_staf_member == 1 AND in_array($contact_id, array($ditjaar_kern1_id, $ditjaar_kern2_id, $ditjaar_kern3_id))) {
            permissions_add($contact_id, $drupal_id, $displayname, $acl_array);
        } else {
            permissions_rem($contact_id, $drupal_id, $displayname, $acl_array);
        }
    }

    if ($group_bestuur_member == 1 OR $group_staf_member == 1 OR $ditjaarleidyes == 1) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 6.1 REGISTER KAMPLEIDING VOOR DE TRAININGSDAG", "$displayname ($ditjaar_pos_leid_kampfunctie)");
        wachthond($extdebug,2, "########################################################################");

        ##########################################################################################
        # RETREIVE EVENT ID'S FROM CACHE
        ##########################################################################################

        $events_cache = find_eventids();                // Haalt alles op (uit cache of vers)
        $kampids_toer = $events_cache['toer'] ?? [];    // Pak specifiek de Toerusting events
        wachthond($extdebug,3, 'kampids_toer',          $kampids_toer);
        wachthond($extdebug,3, 'evtcv_toer_array',      $evtcv_toer_array);

        if (!empty($kampids_toer)) {

            # REGISTREER ALLEEN DE EVENT IDS DIE NOG NIET GEREGISTREERD ZIJN
            $evtcv_toer_notregistered_array = array_diff($kampids_toer, $evtcv_toer_array);
            arsort($evtcv_toer_notregistered_array); // sort by value (reverse)

            wachthond($extdebug,1, "kampids_toer",                      $kampids_toer);
            wachthond($extdebug,1, "evtcv_toer_array",                  $evtcv_toer_array);
            wachthond($extdebug,1, "evtcv_toer_notregistered_array",    $evtcv_toer_notregistered_array);
            wachthond($extdebug,1, "ditevent_part_functie",             $ditjaar_pos_leid_kampfunctie);

            foreach ($evtcv_toer_notregistered_array as $kamptoereid) {
                $params_part_ditevent_create = [
                    'checkPermissions' => FALSE,
                    'debug'  => $apidebug,
                    'values' => [
                        'contact_id'        => $contact_id,
                        'event_id'          => $kamptoereid,
                        'register_date'     => $today_datetime,
                        'status_id'         => 24,  // initiele status: nog niet bekend   
                        'role_id'           => [7], // rol = deelnemer
                    ],
                ];
                wachthond($extdebug,3, 'params_participant_create',                     $params_part_ditevent_create);
                if ($regpast == 1 AND $kamptoereid > 0) {
                    $result_participant_create = civicrm_api4('Participant','create',   $params_part_ditevent_create);
                    wachthond($extdebug,3, "Deze persoon geregistreerd training eid",   $kamptoereid);
                    wachthond($extdebug,9, 'result_participant_create',                 $result_participant_create);
                }
            }
        }
    }

    if ($group_bestuur_member == 1 OR $group_staf_member == 1) {

        wachthond($extdebug,2, "########################################################################");
        wachthond($extdebug,1, "### ACL 6.2 REGISTER HOOFDLEIDING VOOR FUTURE KAMPSTAF EVENTS", "$displayname ($ditjaar_pos_leid_kampfunctie)");
        wachthond($extdebug,2, "########################################################################");

        // M61: DISABLE FOR NOW (123)

        if ($group_staf_member == 123 AND $group_kamp_member == 1 AND $ditjaarleidyes == 1) {

            ##########################################################################################
            # RETREIVE EVENT ID'S FROM CACHE
            ##########################################################################################

            $events_cache = find_eventids();                // Haalt alles op (uit cache of vers)
            $kampids_meet = $events_cache['meet'] ?? [];    // Pak specifiek de Kampstaf/Meetup events
            wachthond($extdebug,3, 'kampids_meet',          $kampids_meet);

            # REGISTREER ALLEEN DE EVENT IDS DIE NOG NIET GEREGISTREERD ZIJN
            $evtcv_meet_notregistered_array = array_diff($kampids_meet, $evtcv_meet_array);
            arsort($evtcv_meet_notregistered_array); // sort by value (reverse)

            wachthond($extdebug,1, "evtcv_meet_array",                  $evtcv_meet_array);
            wachthond($extdebug,1, "kampids_meet",                      $kampids_meet);
            wachthond($extdebug,1, "evtcv_meet_notregistered_array",    $evtcv_meet_notregistered_array);

            wachthond($extdebug,1, "ditevent_part_functie",             $ditjaar_pos_leid_kampfunctie);
            wachthond($extdebug,1, "group_staf_member",                 $group_staf_member);

            if (in_array($ditjaar_pos_leid_kampfunctie, array('hoofdleiding','bestuurslid','kampstaf')) AND $group_staf_member == 1) {

                // ALLEEN INDIEN HL & IN MANUAL ACL GROUP STAF

                foreach ($evtcv_meet_notregistered_array as $kampstafeid) {
                    $params_part_ditevent_create = [
                        'checkPermissions' => FALSE,
                        'debug' => $apidebug,
                        'values' => [
                            'contact_id'        => $contact_id,
                            'event_id'          => $kampstafeid,
                            'register_date'     => $today_datetime,
                            'status_id'         => 24,  // initiele status: nog niet bekend   
                            'role_id'           => [6], // rol = leiding
                        ],
                    ];
                    wachthond($extdebug,7, 'params_participant_create',                     $params_part_ditevent_create);
                    if ($regpast == 1 AND $kampstafeid > 0) {
                        $result_participant_create = civicrm_api4('Participant','create',   $params_part_ditevent_create);
                    }
                    wachthond($extdebug,3, "Deze persoon geregistreerd kampstaf eventid", $kampstafeid);
                    wachthond($extdebug,9, 'result_participant_create', $result_participant_create);
                }
            }
        }

        wachthond($extdebug,3, 'group_staf_member',     $group_staf_member);
        wachthond($extdebug,3, 'group_kamp_member',     $group_kamp_member);
        wachthond($extdebug,3, 'ditjaarleidyes',        $ditjaarleidyes);
        wachthond($extdebug,3, 'ditevent_part_functie', $ditjaar_pos_leid_kampfunctie);
    }

    if ($group_staf_member == 1 AND $group_kamp_member == 1 AND in_array($ditjaar_pos_leid_kampfunctie, array('hoofdleiding','kernteamlid','bestuurslid','kampstaf'))) {

        wachthond($extdebug, 2, "########################################################################");
        wachthond($extdebug, 1, "### ACL 6.3 UPDATE EMAIL NOTIFICATIES EN PARTICIPANT VOOR $displayname");
        wachthond($extdebug, 2, "########################################################################");

        // 1. Basis e-mails bepalen
        $email_onvr_email = $email_onvr_email ?? NULL; 
        $email_home_email = $email_home_email ?? NULL; // Zorg dat deze beschikbaar is
        $email_priv_email = (!empty($email_priv_email)) ? $email_priv_email : $email_home_email;

        wachthond($extdebug,3, 'email_home_email',          $email_home_email);
        wachthond($extdebug,3, 'email_othr_email',          $email_othr_email);
        wachthond($extdebug,3, 'email_priv_email',          $email_priv_email);
        wachthond($extdebug,3, 'notif_deel_email',          $notif_deel_email);
        wachthond($extdebug,3, 'notif_leid_email',          $notif_leid_email);
        wachthond($extdebug,3, 'notif_kamp_email',          $notif_kamp_email);
        wachthond($extdebug,3, 'notif_staf_email',          $notif_staf_email);

        $contact_id             = $array_contditjaar['contact_id']              ?? NULL;
        $displayname            = $array_contditjaar['displayname']             ?? NULL;
        $user_name              = $array_contditjaar['crm_drupalnaam']          ?? NULL;
        $birth_date             = $array_contditjaar['birth_date']              ?? NULL;
        $privacy_voorkeuren     = $array_contditjaar['privacy_voorkeuren']      ?? NULL;

        wachthond($extdebug,3, 'contact_id',                $contact_id);
        wachthond($extdebug,3, 'displayname',               $displayname);
        wachthond($extdebug,3, 'user_name',                 $user_name);
        wachthond($extdebug,3, 'birth_date',                $birth_date);
        wachthond($extdebug,3, 'privacy_voorkeuren',        $privacy_voorkeuren);

        $cont_notificatie_deel  = $array_contditjaar['cont_notificatie_deel']   ?? NULL;
        $cont_notificatie_leid  = $array_contditjaar['cont_notificatie_leid']   ?? NULL;
        $cont_notificatie_kamp  = $array_contditjaar['cont_notificatie_kamp']   ?? NULL;
        $cont_notificatie_staf  = $array_contditjaar['cont_notificatie_staf']   ?? NULL;

        wachthond($extdebug,3, 'cont_notificatie_deel',     $cont_notificatie_deel);
        wachthond($extdebug,3, 'cont_notificatie_leid',     $cont_notificatie_leid);
        wachthond($extdebug,3, 'cont_notificatie_kamp',     $cont_notificatie_kamp);
        wachthond($extdebug,3, 'cont_notificatie_staf',     $cont_notificatie_staf);        

        // 2. Mapping van instellingen (Prioriteit Participant > Contact > Default)
        $notif_map = [
            'notif_deel' => $ditevent_part_notificatie_deel ?? $array_contditjaar['cont_notificatie_deel'] ?? 'privemail',
            'notif_leid' => $ditevent_part_notificatie_leid ?? $array_contditjaar['cont_notificatie_leid'] ?? 'privemail',
            'notif_kamp' => $ditevent_part_notificatie_kamp ?? $array_contditjaar['cont_notificatie_kamp'] ?? 'privemail',
            'notif_staf' => $ditevent_part_notificatie_staf ?? $array_contditjaar['cont_notificatie_staf'] ?? 'privemail',
        ];

        // 3. Lus door de notificatietypes
        foreach ($notif_map as $type_label => $voorkeur) {
            
            $target_email = NULL;
            if ($voorkeur == 'kamppers') {
                $target_email = $email_onvr_email;
            } elseif ($voorkeur == 'privemail') {
                $target_email = $email_priv_email;
            }

            // Alleen uitvoeren voor HL/Kernteam en als er een emailadres is om op te slaan
            if ($target_email && in_array($ditjaar_pos_leid_kampfunctie, ['hoofdleiding', 'kernteamlid'])) {
                
                // PARAMS VOOR SAVE (Creëert als het niet bestaat, update als het wel bestaat)
                $params_email_save = [
                    'checkPermissions' => FALSE,
                    'debug'     => $apidebug,
                    'records'   => [
                        [
                            'location_type_id:name' => $type_label,
                            'email'         => $target_email,
                            'contact_id'    => $contact_id,
                            'is_primary'    => FALSE,
                        ],
                    ],
                    // De 'match' zorgt ervoor dat hij kijkt naar bestaande records voor dit contact en dit type
                    'match' => ['contact_id', 'location_type_id'],
                ];

                wachthond($extdebug, 7, "params_email_{$type_label}_save", $params_email_save);

                if ($extwrite == 1 AND !in_array($privacy_voorkeuren, ["33", "44"])) {
                    $result_save = civicrm_api4('Email', 'save', $params_email_save);
                    wachthond($extdebug, 9, "result_email_{$type_label}_save", $result_save);
                    wachthond($extdebug, 2, "$type_label opgeslagen (save) voor", $target_email);
                }
            }
        }

        // 4. Update Participant Record
        if ($ditevent_part_id && in_array($ditjaar_pos_leid_kampfunctie, ['hoofdleiding', 'kernteamlid'])) {
            $params_part_notif = [
                'checkPermissions'  => FALSE,
                'debug'  => $apidebug,          
                'reload' => TRUE,
                'where'  => [['id', '=', $ditevent_part_id]],
                'values' => [
                    'PART_LEID_HOOFD.notificatie_deel' => $notif_map['notif_deel'], 
                    'PART_LEID_HOOFD.notificatie_leid' => $notif_map['notif_leid'], 
                    'PART_LEID_HOOFD.notificatie_kamp' => $notif_map['notif_kamp'], 
                    'PART_LEID_HOOFD.notificatie_staf' => $notif_map['notif_staf'],
                ],
            ];

            wachthond($extdebug, 7, 'params_part_notif', $params_part_notif);
            
            if ($extwrite == 1) {
                $result_part_notif = civicrm_api4('Participant', 'update', $params_part_notif);
                wachthond($extdebug, 9, 'result_part_notif', $result_part_notif);
            }
        }
    }

    wachthond($extdebug,1, "########################################################################");
    wachthond($extdebug,1, "### ACL - EINDE ACL CHECK VOOR $displayname $ditjaar_pos_kampfunctie $ditjaar_pos_kampkort", "[cid: $contact_id]");
    wachthond($extdebug,1, "########################################################################");     

}

function acl_group_remove($contactid, $group, $group_label) {

    $extdebug       = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug       = FALSE;
    $extwrite       = 1;

    $contact_id     = $contactid;

    if (is_numeric($group)) {
        $group_array    = array($group);
        wachthond($extdebug, 3, "group id",      $group);
    } else {
        $group_array    = $group;
        wachthond($extdebug, 3, "group array",   $group);
    }

    $group_label    = $group_label;         // M61: bij alle functies mss nog input validatie

    wachthond($extdebug, 4, "contact_id",    $contact_id);
    wachthond($extdebug, 4, "group_array",   $group_array);

    if ($contact_id AND $group_array) {

        wachthond($extdebug, 3, "########################################################################");
        wachthond($extdebug, 3, "### ACL - VERWIJDER CID $contact_id UIT GROEPEN $group_label",  $group_array);
        wachthond($extdebug, 3, "########################################################################");

        // Gebruik 'update' met de juiste $params structuur om de status te wijzigen
        $params_groupcontact_remove = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'where' => [
                ['group_id', 'IN', $group_array], 
                ['contact_id', '=', $contact_id],
            ],
            'values' => [
                'status' => 'Removed',
            ],
        ];

        wachthond($extdebug, 7, 'params_groupcontact_remove', $params_groupcontact_remove);

        if ($extwrite == 1 AND $group_array) {
            // Voer de update uit. Door de params direct mee te geven vermijden we 'setValues'
            $result_remove = civicrm_api4('GroupContact', 'update', $params_groupcontact_remove);
            
            wachthond($extdebug, 1, 'Verwijderd uit ACL groep', $group_label);
            wachthond($extdebug, 9, 'result_groupcontact_update', $result_remove);
        }

    } else {
        wachthond($extdebug, 1, 'Verwijderd uit ACL groep [SKIPPED]', $group_label);
    }
}

function acl_group_update($contactid, $group_id, $group_label) {

    $extdebug       = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug       = FALSE;
    $extwrite       = 1;

    $contact_id     = $contactid;
    $group_id       = $group_id;
    $group_label    = $group_label;

    if ($contact_id AND $group_id) {

        wachthond($extdebug,3, "########################################################################");
        wachthond($extdebug,3, "### ACL - UPDATE CID $contact_id IN GROEP $group_label",        $group_id);
        wachthond($extdebug,3, "########################################################################");

        $params_groupcontact_update = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
             'values' => [
                'status' => 'Added',
            ],
            'where' => [
                ['group_id',    '=',    $group_id], 
                ['contact_id',  '=',    $contact_id],
            ],
        ];
        wachthond($extdebug,7, 'params_groupcontact_update',        $params_groupcontact_update);
        if ($extwrite == 1 AND $group_id) {
            $result_update = civicrm_api4('GroupContact','update',  $params_groupcontact_update);
            wachthond($extdebug,1, 'Weer toegevoegd aan ACL groep', $group_label);
        }
    }
}

function acl_group_create($contactid, $group_id, $group_label) {

    $extdebug       = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results
    $apidebug       = FALSE;
    $extwrite       = 1;

    $contact_id     = $contactid;
    $group_id       = $group_id;
    $group_label    = $group_label;

    if ($contact_id AND $group_id) {

        wachthond($extdebug,3, "########################################################################");
        wachthond($extdebug,3, "### ACL - CREATE CID $contact_id IN GROEP $group_label",        $group_id);
        wachthond($extdebug,3, "########################################################################");

        $params_groupcontact_create = [
            'checkPermissions' => FALSE,
            'debug' => $apidebug,
            'values' => [
                'group_id'      => $group_id,
                'contact_id'    => $contact_id,
                'status'        => 'Added',
            ],
        ];
        wachthond($extdebug,7, 'params_groupcontact_create',        $params_groupcontact_create);
        if ($extwrite == 1 AND $group_id) {
            $result_create = civicrm_api4('GroupContact','create',  $params_groupcontact_create);
            wachthond($extdebug,1, 'Nieuw toegevoegd aan ACL groep',$group_label);
        }
    }
}

function acl_group_get($contactid, $group_id, $group_label) {

    $extdebug = 0;
    
    // 1. Statische cache: Dit blijft in het geheugen zolang het script draait
    static $contact_groups_cache = [];

    // 2. Als we de groepen voor dit contact nog niet hebben, haal ze ALLEMAAL in 1x op
    if (!isset($contact_groups_cache[$contactid])) {
        
        wachthond($extdebug,3, "########################################################################");
        wachthond($extdebug,3, "### ACL - GET INFO ABOUT CID $contact_id IN GROEP $group_label",$group_id);
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
    $fetched_label  = $group_label; // Fallback

    if ($group_data) {
        // We hebben een record gevonden (Added, Removed of Pending)
        $group_count    = 1;
        $group_status   = $group_data['status'];
        $fetched_label  = $group_data['label']; // Gebruik de echte naam uit Civi

        if ($group_status == 'Added') {
            $group_member = 1;
        }
    }

    // 4. Return array opbouwen (exact zoals de oude functie deed)
    $group_array = array(
        'contact_id'   => $contactid,
        'group_status' => $group_status,
        'group_label'  => $fetched_label, // Geeft nu de echte groepsnaam terug
        'group_count'  => $group_count,   // 1 als er history is, 0 als nooit lid geweest
        'group_member' => $group_member,  // 1 als NU lid (Added), anders 0
    );

    // Logging beperken om logboek niet te vervuilen (alleen bij status change of debug level 3)
    if ($extdebug >= 3) {
        // wachthond($extdebug, 3, "ACL CACHE CHECK - $group_label ($group_id)", $group_member ? "LID" : "GEEN LID");
    }

    return $group_array;
}

function cms_rol_check($drupal_id, $displayname, $cmsrol) {

    $extdebug    = 0;
    $userhasrole = 0;

    // Cache voor Role ID's (Naam -> ID mapping)
    static $role_map_cache = [];

    if (empty($drupal_id) OR empty($cmsrol)) {
        return 0;
    }

    // 1. Drupal User laden (Drupal heeft hier zelf al een redelijke cache voor, dus ok)
    $cmsuser = user_load($drupal_id);
    if (!$cmsuser) return 0;

    // 2. Haal Role ID op (uit cache of DB)
    if (!isset($role_map_cache[$cmsrol])) {
        $role = user_role_load_by_name($cmsrol);
        if ($role) {
            $role_map_cache[$cmsrol] = $role->rid;
        } else {
            $role_map_cache[$cmsrol] = FALSE; // Rol bestaat niet in systeem
        }
    }
    
    $rid = $role_map_cache[$cmsrol];

    // 3. Checken maar
    if ($rid && isset($cmsuser->roles[$rid])) {
        $userhasrole = 1;
    }

    return $userhasrole;
}

function cms_rol_add($drupal_id, $displayname, $cmsrol) {

    $extdebug       = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results

    wachthond($extdebug,1, 'drupal_id',     $drupal_id);
    wachthond($extdebug,1, 'displayname',   $displayname);

    if (!$drupal_id OR !$cmsrol) {
        wachthond($extdebug,1, 'cms_rol_add', 'input parameters missing');
        return;
    }

    wachthond($extdebug,3, "########################################################################");
    wachthond($extdebug,3, "### ACL - TOEVOEGEN ROL $cmsrol AAN UID $drupal_id",         $displayname);
    wachthond($extdebug,3, "########################################################################");

//  $drupal_role_change     = drupal_role_change($drupal_id, $cmsrol, 'ADD');
//  wachthond($extdebug,1, "Drupal rol $cmsrol aangepast", "[ADDED]");

    if ($role = user_role_load_by_name($cmsrol)) {
        user_multiple_role_edit(array($drupal_id), 'add_role', $role->rid);
    }

}

function cms_rol_remove($drupal_id, $displayname, $cmsrol) {

    $extdebug       = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results

    wachthond($extdebug,1, 'drupal_id',     $drupal_id);
    wachthond($extdebug,1, 'displayname',   $displayname);

    if (!$drupal_id OR !$cmsrol) {
        wachthond($extdebug,1, 'cms_rol_remove', 'input parameters missing');        
        return;
    }

    wachthond($extdebug,3, "########################################################################");
    wachthond($extdebug,3, "### ACL - VERWIJDEREN ROL $cmsrol VAN UID $drupal_id",       $displayname);
    wachthond($extdebug,3, "########################################################################");

//  $drupal_role_change     = drupal_role_change($drupal_id, $cmsrol, 'REMOVE');
//  wachthond($extdebug,1, "Drupal rol $cmsrol aangepast", "[REMOVED]");

    if ($role = user_role_load_by_name($cmsrol)) {
        user_multiple_role_edit(array($drupal_id), 'remove_role', $role->rid);
    }

}

function permissions_add($contact_id, $drupal_id, $displayname, $acl_array) {

    $extdebug = 0; // 1 = basic // 2 = verbose // 3 = params / 4 = results

    // 1. Variabelen uitpakken
    $aclgroup         = $acl_array['aclgroup']         ?? NULL;
    $cmsrol           = $acl_array['cmsrol']           ?? NULL;
    $rolname          = $acl_array['rolname']          ?? NULL;
    $acl_group_label  = $acl_array['acl_group_label']  ?? NULL;
    $cms_hasrol       = $acl_array['cms_hasrol']       ?? NULL;

    // Veiligheidscheck: zonder ID's kunnen we niets
    if (empty($contact_id) || empty($aclgroup)) {
        return;
    }

    // -------------------------------------------------------------------------
    // STAP 1: DE "DOUBLE CHECK" (CiviCRM Groep)
    // -------------------------------------------------------------------------
    // We vertrouwen de cache niet voor schrijfoperaties. We vragen de LIVE status op.
    
    $check = civicrm_api4('GroupContact', 'get', [
        'checkPermissions' => FALSE,
        'select' => ['status'],
        'where' => [
            ['contact_id', '=', $contact_id],
            ['group_id', '=', $aclgroup]
        ],
        'limit' => 1
    ])->first();

    $real_status = $check['status'] ?? 'None';

    // -------------------------------------------------------------------------
    // STAP 2: DE UPDATE (Alleen als nodig)
    // -------------------------------------------------------------------------
    
    // Alleen schrijven als de persoon nog NIET op 'Added' staat.
    // Dit voorkomt nutteloze regels in subscription_history.
    if ($real_status !== 'Added') {

        wachthond($extdebug, 1, "########################################################################");
        wachthond($extdebug, 1, "### ACL - WRITE ACTIE: $displayname -> Groep $acl_group_label ($aclgroup)", "[Was: $real_status]");
        wachthond($extdebug, 1, "########################################################################");

        // Gebruik 'save': dit werkt voor zowel nieuwe toevoegingen als her-activeren van verwijderde leden
        $result = civicrm_api4('GroupContact', 'save', [
            'checkPermissions' => FALSE,
            'records' => [
                [
                    'contact_id' => $contact_id,
                    'group_id'   => $aclgroup,
                    'status'     => 'Added'
                ]
            ]
        ]);
        
        wachthond($extdebug, 9, 'API Result GroupContact Save', $result);
    } 
    else {
        // Debugging (optioneel): laat zien dat we niets doen
        // wachthond($extdebug, 3, "Skipped ACL add (already member)", "$displayname -> $aclgroup");
    }

    // -------------------------------------------------------------------------
    // STAP 3: DRUPAL ROL (CMS)
    // -------------------------------------------------------------------------
    
    if ($drupal_id > 0 && !empty($cmsrol)) {
        // Hier vertrouwen we nog wel op de input parameter $cms_hasrol omdat Drupal calls zwaarder zijn,
        // maar idealiter zou je hier ook user_load kunnen doen voor 100% zekerheid.
        if ($cms_hasrol != 1) {
            cms_rol_add($drupal_id, $displayname, $cmsrol);
        }
    }
}

function permissions_rem($contact_id, $drupal_id, $displayname, $acl_array) {

    $extdebug = 0; // 1 = basic // 2 = verbose // 3 = params / 4 = results

    // 1. Variabelen uitpakken
    $aclgroup         = $acl_array['aclgroup']         ?? NULL;
    $cmsrol           = $acl_array['cmsrol']           ?? NULL;
    $rolname          = $acl_array['rolname']          ?? NULL;
    $acl_group_label  = $acl_array['acl_group_label']  ?? NULL;
    $cms_hasrol       = $acl_array['cms_hasrol']       ?? NULL;

    // Veiligheidscheck
    if (empty($contact_id) || empty($aclgroup)) {
        return;
    }

    // -------------------------------------------------------------------------
    // STAP 1: DE "DOUBLE CHECK" (CiviCRM Groep)
    // -------------------------------------------------------------------------
    // We checken de LIVE status in de database (cache negeren!)
    
    $check = civicrm_api4('GroupContact', 'get', [
        'checkPermissions' => FALSE,
        'select' => ['status'],
        'where' => [
            ['contact_id',  '=', $contact_id],
            ['group_id',    '=', $aclgroup]
        ],
        'limit' => 1
    ])->first();

    $real_status = $check['status'] ?? 'None';

    // -------------------------------------------------------------------------
    // STAP 2: DE VERWIJDER ACTIE (Alleen als nodig)
    // -------------------------------------------------------------------------
    
    // We voeren de actie ALLEEN uit als de persoon 'Added' of 'Pending' is.
    // Als de status al 'Removed' of 'None' is, doen we niets.
    if (in_array($real_status, ['Added', 'Pending'])) {

        wachthond($extdebug, 1, "########################################################################");
        wachthond($extdebug, 1, "### ACL - REMOVE ACTIE: $displayname uit Groep $acl_group_label ($aclgroup)", "[Was: $real_status]");
        wachthond($extdebug, 1, "########################################################################");

        // In APIv4 verwijdert 'delete' het actieve lidmaatschap (zet status op Removed in history)
        $result = civicrm_api4('GroupContact', 'delete', [
            'checkPermissions' => FALSE,
            'where' => [
                ['contact_id',  '=', $contact_id],
                ['group_id',    '=', $aclgroup]
            ]
        ]);
        
        wachthond($extdebug, 9, 'API Result GroupContact Delete', $result);
    } 
    else {
        // Debugging (optioneel):
        // wachthond($extdebug, 3, "Skipped ACL remove (already removed)", "$displayname -> $aclgroup");
    }

    // -------------------------------------------------------------------------
    // STAP 3: DRUPAL ROL (CMS)
    // -------------------------------------------------------------------------
    
    if ($drupal_id > 0 && !empty($cmsrol)) {
        // Hier is het ook veiliger om even vers te checken i.p.v. te vertrouwen op de input variabele
        $user_has_role_now = cms_rol_check($drupal_id, $displayname, $cmsrol);
        
        if ($user_has_role_now == 1) {
            cms_rol_remove($drupal_id, $displayname, $cmsrol);
        }
    }
}

function googlegroup_subscribe($googlegroup_id, $googlegroup_batch) {

    $extdebug           = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results

    $params_googlegroup_subscribe = [
        'group_id'      => $googlegroup_id,
        'emails'        => $googlegroup_batch,
    ];
    try{
        wachthond($extdebug,2, 'params_googlegroup_subscribe',                      $params_googlegroup_subscribe);
        $result_googlegroup_subscribe = civicrm_api3('Googlegroups','subscribe',    $params_googlegroup_subscribe);
        wachthond($extdebug,3, 'result_googlegroup_subscribe',                      $result_googlegroup_subscribe);
    }
    catch (CiviCRM_API3_Exception $e) {
        // Handle error here.
        $errorMessage = $e->getMessage();
        $errorCode    = $e->getErrorCode();
        $errorData    = $e->getExtraParams();
        wachthond($extdebug,1, "ERRORCODE: $errorCode", $errorMessage);
    }
}

function googlegroup_deletemember($googlegroup_id, $googlegroup_batch) {

    $extdebug           = 0;          // 1 = basic // 2 = verbose // 3 = params / 4 = results

    $params_googlegroup_deletemember = [
        'group_id'      => $googlegroup_id,
        'member'        => $googlegroup_batch,
    ];
    try{
        wachthond($extdebug,2, 'params_googlegroup_deletemember',                      $params_googlegroup_deletemember);
        $result_googlegroup_deletemember = civicrm_api3('Googlegroups','deletemember', $params_googlegroup_deletemember);
        wachthond($extdebug,3, 'result_googlegroup_deletemember',                      $result_googlegroup_deletemember);
    }
    catch (CiviCRM_API3_Exception $e) {
        // Handle error here.
        $errorMessage = $e->getMessage();
        $errorCode    = $e->getErrorCode();
        $errorData    = $e->getExtraParams();
        wachthond($extdebug,1, "ERRORCODE: $errorCode", $errorMessage);
    }
}