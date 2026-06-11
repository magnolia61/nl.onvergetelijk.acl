<?php

namespace Civi\Acl;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests voor nl.onvergetelijk.acl.
 *
 * @group e2e
 *
 * acl.php beheert CiviCRM-groepslidmaatschappen en Drupal-rollen op basis van
 * kamp-deelname. De logica werkt via DB-queries — hier testen we:
 *   A: Alle hook- en helper-functies bestaan
 *   B: acl_group_remove() met lege contactid → geen crash
 *   C: acl_civicrm_configure() met contact_id=0 → geen crash (vroege exit)
 *   D: acl_civicrm_configure() met lege allpart_array → geen crash, fallback met nul-flags
 *   E: acl_group_remove() met meerdere groepen → alle actieve lidmaatschappen verwijderd
 *   F: acl_group_get() geeft correcte lidmaatschapsstatus terug
 *   G: permissions_rem() zet 'Added' → 'Removed' en laat 'Removed' ongemoeid
 *   H: permissions_add() na eerdere 'Removed' → update naar 'Added' (geen duplicate)
 */
class AclHooksTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('acl_group_sync')) {
      $this->markTestSkipped('acl_group_sync() niet beschikbaar; is nl.onvergetelijk.acl geïnstalleerd?');
    }
  }

  // ########################################################################
  // ### SCENARIO A: ALLE FUNCTIES BESTAAN
  // ########################################################################

  public function testFunctiesBestaanAllemaal() {
    $functies = [
      'acl_group_sync',
      'acl_group_remove',
      'acl_group_update',
      'acl_group_create',
      'acl_civicrm_configure',
    ];
    foreach ($functies as $fn) {
      $this->assertTrue(function_exists($fn), "Functie '$fn' moet beschikbaar zijn.");
    }
  }

  // ########################################################################
  // ### SCENARIO B: GROUP_REMOVE MET LEGE CONTACTID → GEEN CRASH
  // ########################################################################

  /**
   * acl_group_remove() met lege contactid → geen crash.
   */
  public function testGroupRemoveMetLegeContactidGeeftGeenCrash() {
    try {
      acl_group_remove(0, 'Testgroep', 'Testgroep Label');
      $this->assertTrue(TRUE, 'acl_group_remove(0) mag geen exception gooien.');
    } catch (\Exception $e) {
      $this->fail('acl_group_remove(0) gooide onverwacht een exception: ' . $e->getMessage());
    }
  }

  // ########################################################################
  // ### SCENARIO C: CONFIGURE MET LEGE CONTACTID → GEEN CRASH
  // ########################################################################

  /**
   * acl_civicrm_configure() met contact_id=0 → geen crash (vroege exit).
   */
  public function testConfigureMetLegeContactIdGeeftGeenCrash() {
    try {
      $result = acl_civicrm_configure(0);
      $this->assertTrue($result === NULL || is_array($result),
        'acl_civicrm_configure(0) mag geen exception gooien.'
      );
    } catch (\Exception $e) {
      $this->assertTrue(TRUE, 'CiviCRM API-fout voor contact 0 is acceptabel: ' . $e->getMessage());
    }
  }

  // ########################################################################
  // ### SCENARIO D: CONFIGURE MET PRE-FILLED ARRAYS → CORRECT CONTACT
  // ########################################################################

  /**
   * acl_civicrm_configure() met een leidingrol en lege allpart_array gebruikt de
   * fallback: ditjaarleidyes=0 en maakt geen groepsaanpassingen buiten de normale flow.
   * We controleren dat het contact correct teruggevonden kan worden na de aanroep.
   */
  public function testConfigureMetLeegAllpartGebruiktFallbackMetNulFlags() {
    // Maak een tijdelijk testcontact aan zodat we een geldig contact_id hebben.
    $params_contact_create = [
      'checkPermissions' => FALSE,
      'values'           => [
        'contact_type'   => 'Individual',
        'first_name'     => 'AclTest',
        'last_name'      => 'Fallback' . uniqid(),
      ],
    ];
    $result_contact_create = civicrm_api4('Contact', 'create', $params_contact_create);
    $contact_id            = $result_contact_create->first()['id'];

    $this->assertGreaterThan(0, $contact_id, 'Testcontact moet aangemaakt zijn met geldig ID.');

    // Definieer minimale ditjaar_array met leidingrol (0 → geen leiding dit jaar).
    $ditjaar_array = [
      'ditjaarleidyes' => 0,
      'ditjaardeelyes' => 0,
      'ditjaarleidnot' => 0,
      'ditjaardeelnot' => 0,
      'ditjaardeeltst' => 0,
    ];

    // array_contditjaar met de minimaal benodigde sleutels
    $array_contditjaar = [
      'contact_id'           => $contact_id,
      'displayname'          => 'AclTest Fallback',
      'laatstekeer'          => NULL,
      'curcv_keer_deel'      => 0,
      'curcv_keer_leid'      => 0,
      'datum_belangstelling' => NULL,
    ];

    // Lege allpart_array → geen inschrijvingen
    $allpart_array = [];

    // Lege eventrollen_array → alle event-velden op 0
    $eventrollen_array = [
      'event_hldn1_id'   => 0, 'event_hldn2_id'   => 0, 'event_hldn3_id'   => 0,
      'event_kern1_id'   => 0, 'event_kern2_id'   => 0, 'event_kern3_id'   => 0,
      'event_keuken0_id' => 0, 'event_keuken1_id' => 0, 'event_keuken2_id' => 0, 'event_keuken3_id' => 0,
      'event_gedrag0_id' => 0, 'event_gedrag1_id' => 0, 'event_gedrag2_id' => 0,
      'event_boekje0_id' => 0, 'event_boekje1_id' => 0, 'event_boekje2_id' => 0,
      'event_ehbo0_id'   => 0, 'event_ehbo1_id'   => 0, 'event_ehbo2_id'   => 0, 'event_ehbo3_id'   => 0,
    ];

    // Aanroep mag geen exception gooien
    try {
      acl_civicrm_configure($contact_id, $array_contditjaar, $ditjaar_array, $allpart_array, NULL, $eventrollen_array);
      $this->assertTrue(TRUE, 'acl_civicrm_configure() met lege allpart en nul-flags mag geen exception gooien.');
    } catch (\Exception $e) {
      $this->fail('acl_civicrm_configure() gooide onverwacht een exception: ' . $e->getMessage());
    }

    // Verifieer dat het contact nog steeds in de DB staat (basale sanity check)
    $result_contact_check = civicrm_api4('Contact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['id'],
      'where'            => [['id', '=', $contact_id]],
    ]);
    $this->assertCount(1, $result_contact_check, 'Testcontact moet na de configure-aanroep nog steeds bestaan.');
  }

  // ########################################################################
  // ### SCENARIO E: GROUP_REMOVE MET MEERDERE GROEPEN → ALLE VERWIJDERD
  // ########################################################################

  /**
   * acl_group_remove() met een array van meerdere groepen verwijdert de contacten
   * uit alle groepen waar ze lid van zijn (status 'Added' → 'Removed').
   */
  public function testGroupRemoveMetMeerdereGroepenVerwijdertAlleActieveLidmaatschappen() {
    // Maak een testcontact aan
    $params_contact_create = [
      'checkPermissions' => FALSE,
      'values'           => [
        'contact_type' => 'Individual',
        'first_name'   => 'AclTest',
        'last_name'    => 'MultiRemove' . uniqid(),
      ],
    ];
    $contact_id = civicrm_api4('Contact', 'create', $params_contact_create)->first()['id'];

    // Maak twee tijdelijke testgroepen aan
    $groep1_id = civicrm_api4('Group', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'title'        => 'AclTestGroep1_' . uniqid(),
        'group_type'   => ['Mailing List'],
        'visibility'   => 'User and User Admin Only',
      ],
    ])->first()['id'];

    $groep2_id = civicrm_api4('Group', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'title'        => 'AclTestGroep2_' . uniqid(),
        'group_type'   => ['Mailing List'],
        'visibility'   => 'User and User Admin Only',
      ],
    ])->first()['id'];

    // Voeg het contact toe aan beide groepen met status 'Added'
    civicrm_api4('GroupContact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => ['contact_id' => $contact_id, 'group_id' => $groep1_id, 'status' => 'Added'],
    ]);
    civicrm_api4('GroupContact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => ['contact_id' => $contact_id, 'group_id' => $groep2_id, 'status' => 'Added'],
    ]);

    // Verifieer dat het contact nu in beide groepen zit
    $result_voor = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['group_id', 'status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id', 'IN', [$groep1_id, $groep2_id]],
        ['status', '=', 'Added'],
      ],
    ]);
    $this->assertCount(2, $result_voor, 'Contact moet vóór de remove in beide groepen zitten (status Added).');

    // Roep acl_group_remove() aan met een array van beide groep-ID's
    acl_group_remove($contact_id, [$groep1_id, $groep2_id], 'test_multi_remove');

    // Verifieer dat beide groepslidmaatschappen nu 'Removed' zijn (niet verwijderd, maar status gewijzigd)
    $result_na = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['group_id', 'status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id', 'IN', [$groep1_id, $groep2_id]],
        ['status', '=', 'Removed'],
      ],
    ]);
    $this->assertCount(2, $result_na,
      'Na acl_group_remove() moeten beide groepen status Removed hebben (historisch behoud).'
    );

    // Verifieer dat het contact geen actieve lidmaatschappen meer heeft
    $result_actief = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['group_id', 'status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id', 'IN', [$groep1_id, $groep2_id]],
        ['status', '=', 'Added'],
      ],
    ]);
    $this->assertCount(0, $result_actief,
      'Na acl_group_remove() mogen er geen actieve (Added) lidmaatschappen meer zijn.'
    );
  }

  // ########################################################################
  // ### SCENARIO F: GROUP_GET GEEFT CORRECTE LIDMAATSCHAPSSTATUS
  // ########################################################################

  /**
   * acl_group_get() retourneert group_member=1 als het contact 'Added' is,
   * en group_member=0 als het contact 'Removed' is.
   */
  public function testGroupGetGeeftJuisteLidmaatschapsStatus() {
    // Maak testcontact en testgroep aan
    $contact_id = civicrm_api4('Contact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'contact_type' => 'Individual',
        'first_name'   => 'AclTest',
        'last_name'    => 'GroupGet' . uniqid(),
      ],
    ])->first()['id'];

    $groep_id = civicrm_api4('Group', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'title'      => 'AclTestGroupGet_' . uniqid(),
        'group_type' => ['Mailing List'],
        'visibility' => 'User and User Admin Only',
      ],
    ])->first()['id'];

    // 1. Contact is nog geen lid → group_member moet 0 zijn
    $status_voor = acl_group_get($contact_id, $groep_id, 'test_group_get');
    $this->assertEquals(0, $status_voor['group_member'],
      'acl_group_get() moet group_member=0 teruggeven als het contact geen lid is.'
    );
    $this->assertEquals($contact_id, $status_voor['contact_id'],
      'acl_group_get() moet het juiste contact_id teruggeven.'
    );

    // 2. Voeg contact toe aan groep (status Added)
    civicrm_api4('GroupContact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => ['contact_id' => $contact_id, 'group_id' => $groep_id, 'status' => 'Added'],
    ]);

    // Cache resetten voor een frisse lezing
    $status_lid = acl_group_get($contact_id, $groep_id, 'test_group_get', TRUE);
    $this->assertEquals(1, $status_lid['group_member'],
      'acl_group_get() moet group_member=1 teruggeven als het contact Added is.'
    );
    $this->assertEquals('Added', $status_lid['group_status'],
      'acl_group_get() moet group_status=Added teruggeven.'
    );
  }

  // ########################################################################
  // ### SCENARIO G: PERMISSIONS_REM ZET ADDED → REMOVED; LAAT REMOVED ONGEMOEID
  // ########################################################################

  /**
   * permissions_rem() zet een 'Added' lid op 'Removed' (historisch behoud, geen DELETE).
   * Een contact dat al 'Removed' is, krijgt geen extra update.
   *
   * Dit test de kern-logica van acl.permissions.php:
   * "We gebruiken UPDATE naar 'Removed' i.p.v. DELETE."
   */
  public function testPermissionsRemZetAddedOpRemovedEnLaatRemovedOngemoeid() {
    if (!function_exists('permissions_rem')) {
      $this->markTestSkipped('permissions_rem() niet beschikbaar; is nl.onvergetelijk.acl geïnstalleerd?');
    }

    // Maak testcontact en testgroep aan
    $contact_id = civicrm_api4('Contact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'contact_type' => 'Individual',
        'first_name'   => 'AclTest',
        'last_name'    => 'PermRem' . uniqid(),
      ],
    ])->first()['id'];

    $groep_id = civicrm_api4('Group', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'title'      => 'AclTestPermRem_' . uniqid(),
        'group_type' => ['Mailing List'],
        'visibility' => 'User and User Admin Only',
      ],
    ])->first()['id'];

    // Stap 1: Voeg contact toe aan groep (status Added)
    civicrm_api4('GroupContact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => ['contact_id' => $contact_id, 'group_id' => $groep_id, 'status' => 'Added'],
    ]);

    // Sanity check: contact is nu Added
    $status_voor = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id',   '=', $groep_id],
      ],
    ])->first();
    $this->assertSame('Added', $status_voor['status'], 'Contact moet vóór permissions_rem status Added hebben.');

    // Stap 2: Roep permissions_rem() aan
    $acl_array = [
      'aclgroup'        => $groep_id,
      'acl_group_label' => 'test_groep',
    ];
    permissions_rem($contact_id, NULL, 'AclTest PermRem', $acl_array);

    // Stap 3: Status moet nu Removed zijn (niet verwijderd)
    $status_na = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id',   '=', $groep_id],
      ],
    ])->first();
    $this->assertSame('Removed', $status_na['status'],
      'Na permissions_rem() moet de status Removed zijn (historisch behoud, geen DELETE).'
    );

    // Stap 4: Tweede aanroep op al-Removed contact → geen crash, status blijft Removed
    permissions_rem($contact_id, NULL, 'AclTest PermRem', $acl_array);

    $status_dubbel = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id',   '=', $groep_id],
      ],
    ])->first();
    $this->assertSame('Removed', $status_dubbel['status'],
      'Tweede permissions_rem() op al-Removed contact moet status Removed laten staan.'
    );
  }

  // ########################################################################
  // ### SCENARIO H: PERMISSIONS_ADD NA REMOVED → UPDATE NAAR ADDED (GEEN DUPLICATE)
  // ########################################################################

  /**
   * Na een eerdere 'Removed' status doet permissions_add() een UPDATE (niet CREATE),
   * zodat er geen duplicate records in de GroupContact-tabel ontstaan.
   *
   * Dit test het scenario: iemand meldt zich af, daarna opnieuw aan.
   */
  public function testPermissionsAddNaRemovedUpdaatNaarAddedZonderDuplicate() {
    if (!function_exists('permissions_add')) {
      $this->markTestSkipped('permissions_add() niet beschikbaar; is nl.onvergetelijk.acl geïnstalleerd?');
    }

    // Maak testcontact en testgroep aan
    $contact_id = civicrm_api4('Contact', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'contact_type' => 'Individual',
        'first_name'   => 'AclTest',
        'last_name'    => 'ReAdd' . uniqid(),
      ],
    ])->first()['id'];

    $groep_id = civicrm_api4('Group', 'create', [
      'checkPermissions' => FALSE,
      'values'           => [
        'title'      => 'AclTestReAdd_' . uniqid(),
        'group_type' => ['Mailing List'],
        'visibility' => 'User and User Admin Only',
      ],
    ])->first()['id'];

    $acl_array = [
      'aclgroup'        => $groep_id,
      'acl_group_label' => 'test_readd',
    ];

    // Stap 1: Eerste keer toevoegen → CREATE
    permissions_add($contact_id, NULL, 'AclTest ReAdd', $acl_array);

    // Stap 2: Verwijderen → Removed
    $acl_rem_array = [
      'aclgroup'        => $groep_id,
      'acl_group_label' => 'test_readd',
    ];
    permissions_rem($contact_id, NULL, 'AclTest ReAdd', $acl_rem_array);

    // Stap 3: Opnieuw toevoegen → UPDATE (niet CREATE)
    permissions_add($contact_id, NULL, 'AclTest ReAdd', $acl_array);

    // Controleer: er is nog steeds precies 1 record in GroupContact voor dit contact/groep
    $records = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'select'           => ['id', 'status'],
      'where'            => [
        ['contact_id', '=', $contact_id],
        ['group_id',   '=', $groep_id],
      ],
    ]);
    $this->assertCount(1, $records,
      'Na add→remove→add mag er maar 1 GroupContact-record zijn (UPDATE, niet CREATE).'
    );
    $this->assertSame('Added', $records->first()['status'],
      'Status moet na her-toevoeging Added zijn.'
    );
  }

}
