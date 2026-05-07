# nl.onvergetelijk.acl

## Functionele beschrijving

De `acl`-extensie beheert de groepslidmaatschappen en Drupal-rollen van alle deelnemers en begeleiders. Op basis van iemands inschrijvingen, rol en functies op een kamp wordt automatisch bepaald tot welke CiviCRM-groepen en welke Drupal-rollen iemand behoort.

In de praktijk zorgt `acl` er bijvoorbeeld voor dat een begeleider die hoofdleiding is van een specifiek kamp automatisch in de juiste CiviCRM-groep wordt geplaatst, en dat een deelnemer die dit jaar niet meekan uit alle "dit jaar"-groepen wordt verwijderd. De module behandelt ook speciale rollen zoals keukenchef, kernteamleden en stafleden.

## Afhankelijkheden

- `nl.onvergetelijk.base`
- `nl.onvergetelijk.drupal` (voor Drupal-rolbeheer)

---

## Technische documentatie

### Kernfuncties

- `acl_civicrm_custom($op, $groupID, $entityID)` — ingangspunt via custom hook; roept `acl_civicrm_configure` aan voor het betreffende contact.
- `acl_civicrm_configure($contact_id, ...)` — de hoofdmotor (±900 regels). Verwerkt achtereenvolgens:
  1. Inputnormalisatie en data ophalen (contactdata, allpart, PID, statusflags, eventmatrix, Drupal ID)
  2. Configuratiemap opbouwen: een lookup-array die per groep aangeeft of iemand erin moet (true/false)
  3. Membership-logica (presale)
  4. Algemene cleanup: verwijder uit groepen als iemand dit jaar niet meer meekan
  5. "Ooit"-groepen (historische deelname/leiding)
  6. "Dit jaar"-groepen (deelnemer en leiding)
  7. Specifieke rolgroepen: drukwerk, keukenchef, keukenteam, staf, hoofdleiding, kernteam
  8. Event-registratie (toerusting)
- `acl_group_sync($contact_id, $target_group_id, $all_possible_groups, $label_prefix)` — voegt een contact toe aan of verwijdert het uit een groep, met cleanup van alle gerelateerde groepen.
- `acl_group_remove($contactid, $group, $group_label)` — verwijdert een contact uit een specifieke groep.
- `acl_group_update($contactid, $group_id, $group_label)` — werkt groepslidmaatschap bij.
- `cms_rol_check($drupal_id, $displayname, $cmsrol)` — controleert of een Drupal-rol actief is.
- `cms_rol_add($drupal_id, $displayname, $cmsrol)` — voegt een Drupal-rol toe via `drupal_role_change`.

### Hooks geïmplementeerd
- `civicrm_custom`
- `civicrm_config`, `civicrm_install`, `civicrm_enable`

---

*Beheerd door Stichting Onvergetelijke Zomerkampen.*
