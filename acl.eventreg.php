<?php

/**
 * ============================================================================
 * ACL LOGIC: AUTOMATISCHE EVENT-REGISTRATIE
 * ============================================================================
 * Functies voor het automatisch aanmaken van RSVP-registraties bij toerusting-
 * en kampstafmeeting-events op basis van de rol van een contact.
 *
 * Aangeroepen vanuit acl_civicrm_configure() (acl.php), secties 10.0 en 10.1.
 *
 * @functions
 *   acl_eventreg_toerusting()      ACL 10.0 — Trainingsdag & workshops voor leiding/staf/bestuur
 *   acl_eventreg_kampstafmeeting() ACL 10.1 — Kampstafmeetings voor kampstafleden
 * ============================================================================
 */

/**
 * ============================================================================
 * HELPER: AANMAKEN PARTICIPANT RSVP (als die nog niet bestaat)
 * ============================================================================
 * Gedeelde kern voor acl_eventreg_toerusting en acl_eventreg_kampstafmeeting.
 *
 * @param int    $contact_id     CiviCRM contact ID
 * @param int    $eid            Event ID
 * @param string $today_datetime Datum/tijd voor register_date
 * @param string $log_label      Label voor debug-output ('Event' of 'Meeting')
 * @param string $extdebug       Debug-kanaal voor wachthond
 */
function _acl_eventreg_ensure_participant($contact_id, $eid, $today_datetime, $log_label, $extdebug) {

    $params_get = [
        'checkPermissions'  => FALSE,
        'select'            => ['id'],
        'where'             => [
            ['contact_id',  '=', $contact_id],
            ['event_id',    '=', $eid],
        ],
        'limit'             => 1,
    ];

    wachthond($extdebug, 7, "params_{$log_label}_get",  $params_get);
    $result_get = civicrm_api4('Participant', 'get', $params_get);
    wachthond($extdebug, 9, "result_{$log_label}_get",  $result_get);
    $existing   = $result_get->first();

    if (!$existing) {
        $params_create = [
            'checkPermissions'  => FALSE,
            'values'            => [
                'contact_id'    => $contact_id,
                'event_id'      => $eid,
                'register_date' => $today_datetime,
                'status_id'     => 24,  // Nog niet bekend (RSVP uitnodiging)
                'role_id'       => [7], // Deelnemer
            ],
        ];

        wachthond($extdebug, 7, "params_{$log_label}_create",  $params_create);
        $result_create = civicrm_api4('Participant', 'create', $params_create);
        wachthond($extdebug, 9, "result_{$log_label}_create",  $result_create);

        wachthond($extdebug, 1, "ACL SUCCESS: Automatische RSVP aangemaakt voor $log_label $eid", "PID: " . $result_create->first()['id']);
    } else {
        wachthond($extdebug, 3, "ACL SKIP: Registratie voor $log_label $eid bestaat al", "PID: " . $existing['id']);
    }
}

/**
 * ACL 10.0 — Registreer contact automatisch voor toerusting-events (trainingsdag, workshops).
 *
 * Conditie: bestuur, kampstaflid of leiding dit jaar.
 * Iemand kan in hetzelfde jaar zowel leiding als deelnemer zijn (bv. topkamp),
 * daarom geen uitsluiting op deelnemersstatus.
 *
 * @param int    $contact_id        CiviCRM contact ID
 * @param int    $is_bestuur        1 als lid van bestuurgroep
 * @param int    $group_staf_member 1 als lid van kampstaf (groep 456)
 * @param int    $ditjaarleidyes    1 als bevestigd leiding dit jaar
 * @param string $today_datetime    Datum/tijd voor register_date
 * @param string $extdebug          Debug-kanaal voor wachthond
 */
function acl_eventreg_toerusting($contact_id, $is_bestuur, $group_staf_member, $ditjaarleidyes, $today_datetime, $extdebug) {

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### ACL 10.0 EVENT REGISTRATIE (TOERUSTING)");
    wachthond($extdebug, 2, "########################################################################");

    if ($is_bestuur == 1 || $group_staf_member == 1 || $ditjaarleidyes == 1) {

        $events_cache = find_eventids();
        $kampids_toer = $events_cache['toer'] ?? [];

        foreach ($kampids_toer as $eid) {
            if ($eid > 0) {
                _acl_eventreg_ensure_participant($contact_id, $eid, $today_datetime, 'Event', $extdebug);
            }
        }
    }
}

/**
 * ACL 10.1 — Registreer contact automatisch voor kampstafmeeting-events.
 *
 * Conditie: lid van kampstaf (groep 456). Hoofdleiding valt hier ook onder
 * omdat zij lid zijn van dezelfde kampstaf groep.
 *
 * @param int    $contact_id        CiviCRM contact ID
 * @param int    $group_staf_member 1 als lid van kampstaf (groep 456)
 * @param string $today_datetime    Datum/tijd voor register_date
 * @param string $extdebug          Debug-kanaal voor wachthond
 */
function acl_eventreg_kampstafmeeting($contact_id, $group_staf_member, $today_datetime, $extdebug) {

    wachthond($extdebug, 2, "########################################################################");
    wachthond($extdebug, 1, "### ACL 10.1 EVENT REGISTRATIE (KAMPSTAFMEETINGS)");
    wachthond($extdebug, 2, "########################################################################");

    if ($group_staf_member == 1) {

        $events_cache = find_eventids();
        $kampids_meet = $events_cache['meet'] ?? [];

        foreach ($kampids_meet as $eid) {
            if ($eid > 0) {
                _acl_eventreg_ensure_participant($contact_id, $eid, $today_datetime, 'Meeting', $extdebug);
            }
        }
    }
}
