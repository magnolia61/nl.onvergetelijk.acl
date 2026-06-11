<?php

namespace Civi\Acl;

use Civi\Test\EndToEndInterface;
use Civi\Test\TransactionalInterface;

/**
 * Tests voor ACL-groep en Drupal-rol bij het invullen van het belangstellingsformulier.
 *
 * @group e2e
 *
 * Wanneer iemand het webformulier invult en datum_belangstelling wordt gevuld,
 * roept werving_civicrm_customPre() de functie werving_civicrm_acl() aan, die
 * vervolgens permissions_add() triggert voor de 'OOIT Belangstelling [ACL]' groep
 * (groep ID 855) en de Drupal-rol 'ooit_belangstelling'.
 *
 * We testen hier de ACL-laag rechtstreeks via permissions_add() en
 * via de GroupContact API om de DB-staat te verifiëren.
 *
 * Scenario's:
 *   A: permissions_add() voor groep 855 → contact is lid van groep 855
 *   B: Tweede aanroep (al lid) → geen crash, status blijft 'Added'
 *   C: permissions_add() zonder aclgroup → geen crash (vroege exit)
 *   D: acl_group_remove() → contact is geen lid meer
 *   E: acl_group_update() (alias voor permissions_add) → contact is lid
 */
class BelanstellingAclTest extends \PHPUnit\Framework\TestCase implements EndToEndInterface, TransactionalInterface {

  use \Civi\Test\Api3TestTrait;

  private int $contactId;
  private int $gidBelangstelling = 855; // 'OOIT Belangstelling [ACL]'

  public function setUp(): void {
    parent::setUp();
    if (!function_exists('permissions_add')) {
      $this->markTestSkipped('permissions_add() niet beschikbaar; is nl.onvergetelijk.acl geïnstalleerd?');
    }

    // Zorg dat groep 855 bestaat (anders is de test afhankelijk van live data)
    $groep = civicrm_api4('Group', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [['id', '=', $this->gidBelangstelling]],
      'select'           => ['id', 'name'],
    ])->first();

    if (empty($groep)) {
      $this->markTestSkipped("Groep $this->gidBelangstelling ('OOIT Belangstelling [ACL]') bestaat niet in deze omgeving.");
    }

    $this->contactId = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name'   => 'Acl',
      'last_name'    => 'Belangstelling',
    ])['id'];
  }

  // ########################################################################
  // ### SCENARIO A: PERMISSIONS_ADD → CONTACT LID VAN GROEP 855
  // ########################################################################

  /**
   * Na permissions_add() voor groep 855 moet het contact als 'Added' staan.
   * Dit is wat werving_civicrm_acl() doet bij datum_belangstelling gevuld.
   */
  public function testPermissionsAddVoegtContactToeAanGroep855() {
    permissions_add(
      $this->contactId,
      NULL,
      'Acl Belangstelling',
      ['aclgroup' => $this->gidBelangstelling, 'acl_group_label' => 'belangstelling']
    );

    $groupContact = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [
        ['contact_id', '=', $this->contactId],
        ['group_id',   '=', $this->gidBelangstelling],
      ],
      'select'           => ['status'],
    ])->first();

    $this->assertNotEmpty($groupContact,
      "Na permissions_add() moet er een GroupContact-record bestaan voor groep $this->gidBelangstelling.");
    $this->assertSame('Added', $groupContact['status'],
      "GroupContact-status moet 'Added' zijn na permissions_add().");
  }

  // ########################################################################
  // ### SCENARIO B: DUBBELE AANROEP → GEEN CRASH, STATUS BLIJFT ADDED
  // ########################################################################

  /**
   * Een tweede aanroep van permissions_add() voor hetzelfde contact/groep
   * mag geen crash of duplicate veroorzaken.
   */
  public function testDubbelePermissionsAddGeeftGeenCrash() {
    $acl = ['aclgroup' => $this->gidBelangstelling, 'acl_group_label' => 'belangstelling'];

    permissions_add($this->contactId, NULL, 'Test', $acl);
    permissions_add($this->contactId, NULL, 'Test', $acl); // Tweede keer

    $count = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [
        ['contact_id', '=', $this->contactId],
        ['group_id',   '=', $this->gidBelangstelling],
        ['status',     '=', 'Added'],
      ],
    ])->count();

    $this->assertSame(1, $count,
      'Na twee aanroepen mag er maar één GroupContact-record met status Added zijn (geen duplicates).');
  }

  // ########################################################################
  // ### SCENARIO C: GEEN ACLGROUP → VROEGE EXIT, GEEN CRASH
  // ########################################################################

  /**
   * permissions_add() zonder aclgroup in de array → vroege exit, geen crash.
   */
  public function testPermissionsAddZonderAclgroupGeeftGeenCrash() {
    try {
      permissions_add($this->contactId, NULL, 'Test', ['acl_group_label' => 'test_zonder_groep']);
      $this->assertTrue(TRUE, 'permissions_add() zonder aclgroup mag geen exception gooien.');
    } catch (\Exception $e) {
      $this->fail('permissions_add() gooide onverwacht een exception: ' . $e->getMessage());
    }
  }

  // ########################################################################
  // ### SCENARIO D: ACL_GROUP_REMOVE → CONTACT NIET MEER IN GROEP
  // ########################################################################

  /**
   * Na acl_group_remove() moet het contact niet meer als 'Added' staan.
   * Dit simuleert het intrekken van de belangstelling-ACL.
   */
  public function testAclGroupRemoveVerwijdertContactUitGroep() {
    // Eerst toevoegen
    permissions_add(
      $this->contactId,
      NULL,
      'Test',
      ['aclgroup' => $this->gidBelangstelling, 'acl_group_label' => 'belangstelling']
    );

    // Dan verwijderen
    acl_group_remove($this->contactId, $this->gidBelangstelling, 'belangstelling');

    $groupContact = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [
        ['contact_id', '=', $this->contactId],
        ['group_id',   '=', $this->gidBelangstelling],
        ['status',     '=', 'Added'],
      ],
    ])->first();

    $this->assertEmpty($groupContact,
      "Na acl_group_remove() mag de status niet meer 'Added' zijn.");
  }

  // ########################################################################
  // ### SCENARIO E: ACL_GROUP_UPDATE → CONTACT LID (ALIAS)
  // ########################################################################

  /**
   * acl_group_update() is een alias voor permissions_add — zelfde gedrag.
   */
  public function testAclGroupUpdateVoegtContactToeAanGroep() {
    acl_group_update($this->contactId, $this->gidBelangstelling, 'belangstelling');

    $groupContact = civicrm_api4('GroupContact', 'get', [
      'checkPermissions' => FALSE,
      'where'            => [
        ['contact_id', '=', $this->contactId],
        ['group_id',   '=', $this->gidBelangstelling],
      ],
      'select'           => ['status'],
    ])->first();

    $this->assertNotEmpty($groupContact,
      'Na acl_group_update() moet er een GroupContact-record bestaan.');
    $this->assertSame('Added', $groupContact['status'],
      "Status moet 'Added' zijn na acl_group_update().");
  }
}
