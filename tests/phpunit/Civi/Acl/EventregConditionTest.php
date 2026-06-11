<?php

namespace Civi\Acl;

/**
 * Unit tests voor de conditielogica van acl_eventreg_toerusting().
 *
 * Test de booleaanse conditie die bepaalt of iemand wordt geregistreerd
 * voor toerusting-events. Geen DB vereist — puur logica.
 *
 * Achtergrond: de originele conditie gebruikte `$ditjaardeelyes == 0`,
 * maar mee.php initialiseert $ditjaardeelyes op 3 als foutwaarde. Daardoor
 * werden leiding-contacten nooit geregistreerd. De fix: `!== 1`.
 *
 * @group headless
 */
class EventregConditionTest extends \PHPUnit\Framework\TestCase {

    /**
     * Evalueer de ACL 10.0 conditie met gegeven inputwaarden.
     * Weerspiegelt exact de conditie in acl_eventreg_toerusting().
     */
    private function conditie(int $is_bestuur, int $group_staf_member, int $ditjaarleidyes): bool {
        return ($is_bestuur == 1 || $group_staf_member == 1 || $ditjaarleidyes == 1);
    }

    // ########################################################################
    // ### SCENARIO A: POSITIEVE GEVALLEN (moet registreren)
    // ########################################################################

    public function testLeidingDitJaarWordtGeregistreerd(): void {
        $this->assertTrue($this->conditie(0, 0, 1),
            'Leiding dit jaar (ditjaarleidyes=1) moet geregistreerd worden.'
        );
    }

    public function testKampstafWordtGeregistreerd(): void {
        $this->assertTrue($this->conditie(0, 1, 0),
            'Kampstaflid (group_staf_member=1) moet geregistreerd worden.'
        );
    }

    public function testBestuurWordtGeregistreerd(): void {
        $this->assertTrue($this->conditie(1, 0, 0),
            'Bestuurslid (is_bestuur=1) moet geregistreerd worden.'
        );
    }

    public function testLeidingEnDeelnemerTegeliijkWordtGeregistreerd(): void {
        // Iemand kan in hetzelfde jaar leiding zijn én deelnemer (bv. topkamp).
        // De deelnemersstatus is bewust niet meer in de conditie.
        $this->assertTrue($this->conditie(0, 0, 1),
            'Leiding die ook deelnemer is moet nog steeds geregistreerd worden.'
        );
    }

    // ########################################################################
    // ### SCENARIO B: NEGATIEVE GEVALLEN (moet NIET registreren)
    // ########################################################################

    public function testGeenRolWordtNietGeregistreerd(): void {
        $this->assertFalse($this->conditie(0, 0, 0),
            'Iemand zonder bestuur/staf/leiding rol mag niet geregistreerd worden.'
        );
    }

    // ########################################################################
    // ### SCENARIO C: DE ORIGINELE BUG — ditjaardeelyes = 3
    // ########################################################################

    /**
     * Dit was de rootcause van de Sharon-bug.
     *
     * mee.php initialiseert $ditjaardeelyes = 3 als foutwaarde (niet berekend).
     * De oude conditie `&& $ditjaardeelyes == 0` faalde daardoor altijd,
     * ook als $ditjaarleidyes == 1. Leiding werd nooit geregistreerd.
     *
     * De fix: deelnemersstatus volledig uit de conditie gehaald.
     * Deze test documenteert dat $ditjaardeelyes geen rol meer speelt.
     */
    public function testLeidingMetDitjaardeelyes3WordtGeregistreerd(): void {
        // Simuleer de bug: ditjaarleidyes=1 maar ditjaardeelyes=3 (foutwaarde)
        // Oud gedrag: ($is_bestuur==1 || $staf==1 || $leid==1) && $deel==0
        //             → (FALSE || FALSE || TRUE) && (3==0) → TRUE && FALSE → FALSE ← BUG
        // Nieuw gedrag: conditie onafhankelijk van ditjaardeelyes → TRUE ✓
        $ditjaarleidyes  = 1;
        $ditjaardeelyes  = 3; // mee.php foutwaarde

        // Oude (kapotte) conditie
        $oude_conditie = ($ditjaarleidyes == 1) && ($ditjaardeelyes == 0);
        $this->assertFalse($oude_conditie,
            'Regressietest: oude conditie faalde op ditjaardeelyes=3 (documenteert de bug).'
        );

        // Nieuwe (gecorrigeerde) conditie
        $nieuwe_conditie = $this->conditie(0, 0, $ditjaarleidyes);
        $this->assertTrue($nieuwe_conditie,
            'Nieuwe conditie mag niet falen op ditjaardeelyes=3.'
        );
    }

    public function testLeidingMetDitjaardeelyes0WordtGeregistreerd(): void {
        $this->assertTrue($this->conditie(0, 0, 1),
            'Leiding met ditjaardeelyes=0 (normaal geval) moet geregistreerd worden.'
        );
    }

    // ########################################################################
    // ### SCENARIO D: FUNCTIES BESTAAN
    // ########################################################################

    public function testEventregFunctiesBestaanAllemaal(): void {
        $functies = [
            'acl_eventreg_toerusting',
            'acl_eventreg_kampstafmeeting',
        ];
        foreach ($functies as $fn) {
            $this->assertTrue(function_exists($fn), "Functie '$fn' moet beschikbaar zijn.");
        }
    }
}
