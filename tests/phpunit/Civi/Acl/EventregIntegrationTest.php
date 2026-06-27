<?php

namespace Civi\Acl;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Integratietest: ACL-run voor leiding met trainingsdag aanwezig.
 *
 * Verifieert dat als acl_civicrm_configure() draait voor een contact met
 * een actieve leiding-inschrijving, er automatisch een RSVP-registratie
 * voor de trainingsdag wordt aangemaakt (indien het event bestaat).
 *
 * Opstelling:
 *   1. Maak een testcontact aan
 *   2. Maak een leiding-event (type 101 = leidtest) aan
 *   3. Schrijf het contact in als leiding (positieve status)
 *   4. Maak een trainingsdag-event (type 101 = leidtest) aan
 *   5. Injecteer het trainingsdag-event handmatig in de find_eventids-cache
 *      onder de 'toer'-sleutel (zodat acl_eventreg_toerusting hem vindt)
 *   6. Roep acl_civicrm_configure() aan
 *   7. Controleer dat er een participant-record voor de trainingsdag bestaat
 *
 * VEILIGHEID: We gebruiken event_type_id=101 (leidtest) in plaats van de
 * echte type 1 (leiding) en type 3 (trainingsdag). Daardoor triggeren geen
 * productie-CiviRules (uitnodigingsmails) voor echte deelnemers/leiding.
 * De 'toer'-cache wordt handmatig gevuld zodat de ACL-logica toch correct
 * getest wordt.
 *
 * @group e2e
 */
class EventregIntegrationTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

    /** @var int CiviCRM contact ID van het testcontact */
    private int $contact_id;

    /** @var int Event ID van het leiding-event */
    private int $leiding_event_id;

    /** @var int Event ID van de trainingsdag */
    private int $trainingsdag_event_id;

    public function setUp(): void {
        parent::setUp();

        if (!function_exists('acl_civicrm_configure')) {
            $this->markTestSkipped('acl_civicrm_configure() niet beschikbaar; is nl.onvergetelijk.acl geïnstalleerd?');
        }
        if (!function_exists('find_eventids')) {
            $this->markTestSkipped('find_eventids() niet beschikbaar; is nl.onvergetelijk.base geïnstalleerd?');
        }

        $today = date('Y-m-d');

        // ── 1. TESTCONTACT ───────────────────────────────────────────────────
        $contact = civicrm_api4('Contact', 'create', [
            'checkPermissions' => FALSE,
            'values' => [
                'contact_type' => 'Individual',
                'first_name'   => 'Test',
                'last_name'    => 'ACL-Eventreg',
            ],
        ])->first();
        $this->contact_id = $contact['id'];

        // ── 2. LEIDING-EVENT (type 101 = leidtest, veilig: triggert geen productie-CiviRules) ─
        $leiding_event = civicrm_api4('Event', 'create', [
            'checkPermissions' => FALSE,
            'values' => [
                'title'          => 'ACL Verificatie Leiding Zomerkamp',
                'event_type_id'  => 101,   // leidtest — geen productie-CiviRules
                'start_date'     => date('Y-m-d', strtotime('+30 days')),
                'end_date'       => date('Y-m-d', strtotime('+37 days')),
                'is_active'      => TRUE,
                'is_public'      => FALSE,
            ],
        ])->first();
        $this->leiding_event_id = $leiding_event['id'];

        // ── 3. LEIDING-INSCHRIJVING (status Geregistreerd = 1) ──────────────
        civicrm_api4('Participant', 'create', [
            'checkPermissions' => FALSE,
            'values' => [
                'contact_id'    => $this->contact_id,
                'event_id'      => $this->leiding_event_id,
                'status_id'     => 1,    // Geregistreerd
                'role_id'       => [6],  // Leiding (option_value 6; let op: 1 = Bezoeker)
                'register_date' => $today,
            ],
        ]);

        // ── 4. TRAININGSDAG-EVENT (type 101 = leidtest, veilig: triggert geen productie-CiviRules) ─
        //       We injecteren dit event handmatig in de 'toer'-cache zodat acl_eventreg_toerusting()
        //       hem vindt zonder dat we een echt type-3 event hoeven aan te maken.
        $trainingsdag = civicrm_api4('Event', 'create', [
            'checkPermissions' => FALSE,
            'values' => [
                'title'          => 'ACL Verificatie Trainingsdag',
                'event_type_id'  => 101,   // leidtest — geen productie-CiviRules
                'start_date'     => date('Y-m-d', strtotime('+60 days')),
                'end_date'       => date('Y-m-d', strtotime('+60 days')),
                'is_active'      => TRUE,
                'is_public'      => FALSE,
            ],
        ])->first();
        $this->trainingsdag_event_id = $trainingsdag['id'];

        // ── 5. CACHE OPBOUWEN en trainingsdag handmatig in 'toer' injecteren ──
        //       find_eventids() plaatst type-101 niet in 'toer' (dat is voor type 3/5).
        //       We schrijven de cache handmatig zodat acl_eventreg_toerusting() het
        //       trainingsdag-event wél vindt, zonder dat een echt type-3 event CiviRules
        //       triggert. base_find_allpart(force_refresh) vernieuwt de allpart-cache
        //       die door werving-hook bij contact-aanmaak leeg gecacht was.
        \Civi::cache()->delete('cache_all_event_ids_v2');
        $ids = find_eventids();
        $ids['toer'][]  = $this->trainingsdag_event_id;
        $ids['all'][]   = $this->trainingsdag_event_id;
        $ids['leid_all'][] = $this->leiding_event_id;  // type-101 zit in leidtest, niet in leid_all
        $ids['all'][]   = $this->leiding_event_id;
        // Dedupliceer en sorteer
        foreach (['toer', 'all', 'leid_all'] as $k) {
            $ids[$k] = array_values(array_unique($ids[$k]));
            sort($ids[$k], SORT_NUMERIC);
        }
        \Civi::cache()->set('cache_all_event_ids_v2', $ids);

        base_find_allpart($this->contact_id, NULL, TRUE);
    }

    // ########################################################################
    // ### TEST: RSVP TRAININGSDAG AANGEMAAKT NA ACL RUN
    // ########################################################################

    /**
     * Hoofdscenario: leiding krijgt automatisch trainingsdag-registratie.
     */
    public function testLeidingKrijgtTrainingsdagRegistratieNaAclRun(): void {

        // Voer de ACL configuratie uit voor het testcontact
        acl_civicrm_configure($this->contact_id);

        // Controleer dat er een participant-record voor de trainingsdag bestaat
        $registraties = civicrm_api4('Participant', 'get', [
            'checkPermissions' => FALSE,
            'select' => ['id', 'status_id', 'role_id'],
            'where'  => [
                ['contact_id', '=', $this->contact_id],
                ['event_id',   '=', $this->trainingsdag_event_id],
            ],
        ]);

        $this->assertGreaterThan(0, count($registraties),
            "Na acl_civicrm_configure() moet er een participant-record voor de trainingsdag bestaan."
        );

        $reg = $registraties->first();
        $this->assertEquals(24, $reg['status_id'],
            "De automatische RSVP moet status 24 (Nog niet bekend) hebben."
        );

        // Een toerusting/trainingsdag is een STAF-event → de RSVP moet rol Leiding (6)
        // krijgen, NIET Deelnemer (7). Vóór de fix schreef _acl_eventreg_ensure_participant()
        // hard [7] weg, waardoor staf als deelnemer in de lijsten verscheen.
        $rollen = array_map('intval', (array) ($reg['role_id'] ?? []));
        $this->assertContains(6, $rollen,
            "Toerusting-RSVP moet rol Leiding (6) krijgen."
        );
        $this->assertNotContains(7, $rollen,
            "Toerusting-RSVP mag NIET rol Deelnemer (7) krijgen (regressie: hardcoded [7])."
        );
    }

    // ########################################################################
    // ### TEST: GEEN DUBBELE REGISTRATIE BIJ TWEEDE ACL RUN
    // ########################################################################

    /**
     * Idempotentie: een tweede ACL-run mag geen duplicaat aanmaken.
     */
    public function testTweedeAclRunMaaktGeenDuplicaat(): void {

        acl_civicrm_configure($this->contact_id);
        acl_civicrm_configure($this->contact_id);

        $registraties = civicrm_api4('Participant', 'get', [
            'checkPermissions' => FALSE,
            'select' => ['id'],
            'where'  => [
                ['contact_id', '=', $this->contact_id],
                ['event_id',   '=', $this->trainingsdag_event_id],
            ],
        ]);

        $this->assertEquals(1, count($registraties),
            "Na twee ACL-runs mag er maar één trainingsdag-registratie zijn (geen duplicaat)."
        );
    }

    // ########################################################################
    // ### TEST: GEEN REGISTRATIE ALS TRAININGSDAG NIET BESTAAT
    // ########################################################################

    /**
     * Als er geen trainingsdag-event in het boekjaar is, mag er geen crash komen.
     */
    public function testAclRunZonderTrainingsdagGeeftGeenCrash(): void {

        // Verwijder de trainingsdag zodat find_eventids() hem niet vindt.
        // Wis ook de handmatig gepatche cache zodat de rebuild een lege toer-lijst geeft.
        civicrm_api4('Event', 'delete', [
            'checkPermissions' => FALSE,
            'where' => [['id', '=', $this->trainingsdag_event_id]],
        ]);
        \Civi::cache()->delete('cache_all_event_ids_v2');
        base_find_allpart($this->contact_id, NULL, TRUE);

        try {
            acl_civicrm_configure($this->contact_id);
            $this->assertTrue(TRUE, 'ACL-run zonder trainingsdag mag geen exception gooien.');
        } catch (\Exception $e) {
            $this->fail('ACL-run gooide onverwacht een exception: ' . $e->getMessage());
        }
    }
}
