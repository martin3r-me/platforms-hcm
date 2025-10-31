# CSV-Feld Analyse - Fehlende Felder im Import

## ✅ Bereits abgebildet
- PersonalNr, Nachname, Vorname, GeburtsDatum, Geschlecht, Staatsangehoerigkeit
- Aktiv, BeginnTaetigkeit, EndeTaetigkeit
- KostenstellenBezeichner, Taetigkeit, Taetigkeitsbez, Stellenbezeichnung
- Steuerklasse, Kirche, Kinderfreibetrag, Identifikationsnummer
- VersicherungsNr (SV-Nr), VersicherungsStatus
- KrankenkasseBetriebsnummer, KrankenkasseName
- Auszahlungsart, Kontoinhaber, Iban, Swift
- Lohngrundart, Stundenlohn, Grundgehalt
- Urlaub, UrlaubVomVorjahr, UrlaubSchonGenommen, UrlaubverfallenDateFuerMitarbeiter
- ArbeitstageWoche, Wochenstunden
- Tarif, Tarifgruppe, Tarifstufe
- Rabattstyp, MaxAnzahlEssenProMonat
- BROICH Hoodie, Transponder, Spindschlüssel, Zusätzliche Schlüssel, Arbeitskleidung (als Issues)

---

## 🔴 KRITISCH - Sollten abgebildet werden

### Notfallkontakt (auf Employee oder Contact)
- `NotfallAnsprechpartner` - Name
- `NotfallTelefonnummer` - Telefon

### Dienstwagen (auf Contract - wichtiges Benefit!)
- `FirmenPKW` - Ja/Nein
- `Fahrtkosten` - Betrag (verschlüsselt)

### Zusätzliche Personaldaten (auf Employee)
- `Geburtsname` - Maiden Name
- `GeburtsOrt` - Ort
- `Geburtsland` - Land
- `Titel` - Dr., Prof., etc.
- `Vorsatzwort` - Von, Zu, etc.
- `Zusatzwort` - Zweiter Nachname?

### Befristung/Vertragsform (auf Contract)
- `Befristung` - Ja/Nein
- `UrspruenglicheBefristungBis` - Datum
- `VertragsformID` - Lookup oder Text
- `Beschäftigungsverhältnis` - Text

### Probezeit (auf Contract)
- `Probezeit` - Text/Datum

### Arbeitserlaubnis/Aufenthalt (auf Employee - wichtig für Ausländer)
- `UnbefristeteAufenthaltserlaubnis` - Ja/Nein
- `ArbeitserlaubnisBis` - Datum
- `GrenzgaengerLand` - Land

### Behinderung Details (auf Employee - bereits Grad vorhanden)
- `Behindertenausweis` - Ja/Nein
- `Behindertenausweisnummer` - Text
- `Behindertenausweis gültig ab` - Datum
- `Behindertenausweis gültig bis` - Datum
- `ZusatzurlaubSchwerbehinderung` - Tage (auf Contract)

---

## 🟡 WICHTIG - Sollten erwogen werden

### Organisation/Standorte (auf Contract)
- `TaetigkeitstaetteName` - Arbeitsort Name
- `TaetigkeitstaetteStrasse` - Adresse
- `TaetigkeitstaettePLZ` - PLZ
- `TaetigkeitstaetteOrt` - Ort
- `TaetigkeitstaetteBundesland` - Bundesland
- `BetriebsstaetteName` - Betriebsstätte
- `AbteilungID` - Abteilungs-Lookup

### Vorgesetzter/Organisation (auf Employee)
- `Vorgesetzter` - Name/ID (Self-Reference)
- `StellvertreterId` - Employee-ID
- `Alias` - Alternativer Name

### Schulung/Nachweis (auf Employee oder Contract)
- `Hygieneschulung` - Datum/Status
- `ElterneigenschaftNachweis` / `NachweisElterneigenschaft` - Datum/Status

### Rentenversicherung (auf Contract)
- `BetriebsnummerRV` - RV-Betriebsnummer
- `Rentenversicherungsfreiheit` - Ja/Nein
- `RentenversicherungAufstockung` - Betrag?

### Zusätzliche Beschäftigungen (auf Contract)
- `Hauptbeschaeftigt` - Ja/Nein
- `ZusaetzlicheArbeitsvehaeltnisse` - Ja/Nein
- `ZusaetzlicherArbeitgeber1/2` - Text
- `ZusaetzlicherVerdienst1/2` - Betrag (verschlüsselt)
- `AndereArbeitgeber` - Text
- `AndereArbeitgeberGeringfuegig` - Ja/Nein
- `GleichzeitigeBeschaeftigungBeginn` - Datum
- `GleichzeitigeBeschaeftigungEnde` - Datum
- `GleichzeitigeBeschaeftigungEntgelt` - Betrag (verschlüsselt)

### E-Mail Geschäftlich (auf Employee)
- `EMailGeschaeftlich` - E-Mail (privat ist bereits via Contact)

---

## 🟢 NICE-TO-HAVE - Kann später kommen

### Verpflegung Details (auf Contract Attributes)
- `BeginnRabattsVerpflegung` - Datum
- `EssenProArbeitstage` - Anzahl
- `EssenAutomatischeZuweisung` - Ja/Nein
- `ZuArbeitstageGroesserAls` - Zahl
- `Fruestueck`, `Mittagessen`, `Abendessen` - Ja/Nein pro Mahlzeit
- `VerpflegungVersteuerung` - Text
- `VerpflegungAbrechnungsart` - Text
- `VerpflegungVersteuerungProzenteAN/AG` - Prozent

### Zusatzleistungen (auf Contract Attributes)
- `BetrieblicheAltersvorsorge` - Text/Betrag
- `VermoegenswirksameLeistungen` - Betrag (verschlüsselt)
- `Uebungsleiterpauschale` - Betrag
- `Ehrenamtspauschale` - Betrag
- `Kindergartenzuschuss` - Betrag
- `Gutschein` - Text

### Überstunden/Gleittage (auf Contract)
- `UeberstundenUebertragVorjahr` - Stunden/Tage
- `UeberstundenUebertragVorVorjahr` - Stunden/Tage
- `GuttageUebertragVorjahr` - Tage
- `TageSchonGearbeitet` - Tage
- `MehrstundenMaximal` - Stunden
- `MinderstundenMaximal` - Stunden

### Arbeitszeit Details (auf Contract Attributes)
- `ArbeitsStundenProTag` - Stunden
- `Sollzeit`, `MaxSollzeit`, `MindSollzeit` - Stunden
- `KalendarischeArbeitstage` - Tage
- `KalendSollzeit` - Stunden
- `Regelarbeitszeiten` - Text
- `PausenregelungVerwenden` - Ja/Nein
- `PauseBisBereich1-4`, `PausendauerBereich1-4` - Details
- `GebuchtePausenMinuten` - Minuten
- `Pausenabzug` - Text

### Zuschläge (auf Contract Attributes - sehr komplex)
- `Zuschlagsart`, `ZuschlaegeTyp` - Lookup
- `ZuschlagNachtNormal1/2`, `ZuschlagNachtErhoeht` - Prozent
- `ZuschlagSonntag`, `ZuschlagFeiertagNormal1/2`, `Erhoeht1-4` - Prozent
- `ZuschlagNachtNormal1Beginn/Ende` - Zeit
- `AktoProStunde`, `AktoProMonat` - Betrag
- `AktoImMonat` - Betrag
- `MitarbeiterNachtzuschlaegeAbStunden` - Stunden

### Urlaub Details (auf Contract Attributes)
- `JahresurlaubsanspruchManuell` - Tage
- `GuttageregelungVerwenden` - Ja/Nein
- `UrlaubsanspruchBegruendung` - Text
- `MaximalerUrlaubsgeld` - Betrag
- `UebernommenesUrlaubsgeld` - Betrag
- `UrlaubsgeldJanuar-Dezember` - 12 Monate Beträge (verschlüsselt)
- `MitarbeiterUrlaubsberechnungTyp` - Text
- `MitarbeiterUrlaubsberechnungTeilanspruchRegelung` - Text
- `MitarbeiterUrlaubsberechnungWartezeitMonate` - Monate
- `MitarbeiterUrlaubsberechnungAufrundenAb/AbrundenAb` - Tage
- `MitarbeiterUrlaubsberechnungVollerAnspruchZum/Ab` - Datum/Tage

### Kündigung Details (auf Contract Attributes - historisch)
- `BeendigungDurch` - Text
- `KuendigungSchriftlich` - Ja/Nein
- `Ausstellungsdatum`, `Zustellungsdatum` - Datum
- `ZustellungsArt` - Text
- `Kündigungsfrist Anzahl/Zeiteinheit/zum` - Details
- `KopieArbeitsvertrag`, `KopieKuendigung` - Ja/Nein
- `Kuendigungsgrund`, `KuendigungsgrundKommentar` - Text
- `KuendigungInProbezeit` - Ja/Nein
- `KuendigungsschutzklageGemaessKSchG` - Ja/Nein
- `ZusaetzlicheKuendigungsvereinbarungen` - Text
- `SozialauswahlVorgenommen` - Ja/Nein
- `GeprueftDurchArbeitsagentur` - Ja/Nein
- `KuendigungsfristAGAnzahl/AG/AGZum` - Details
- `Kuendigungsausschluss/Text/ZeitlichBegrenzt` - Details
- `OrdentlicheKuendigungNurGegenLeistungZulaessig` - Ja/Nein
- `FristgebKuendigungNurGegenLeistungZulaessig` - Ja/Nein
- `LeistungenBeiBeschEnde` - Text
- `ZusatzleistungenWennUngewissGrund` - Text
- `WeiterzahlungVonEntgeltNachBeschaeftigungsende/Bis` - Details
- `UrlaubsabgeltungWegenBeschEnde/Bis` - Details
- `GewaehrungVonVorruhestandsleistungen/Ab/Brutto` - Details
- `AbfindungBisZuMonatsentgelteProJahr` - Details
- `WennDurchDenArbeitgeberGekuendigtAm/Zum/Abfindung` - Details
- `DeswegenBereitsAbmahnung/Am` - Details

### Freistellung (auf Contract)
- `UnwiderruflicheFreistellungMitWeiterzahlungDesEntgelts` - Ja/Nein
- `BeginnDerUnwiderruflichenFreistellung` - Datum
- `EndeDerUnwiderruflichenFreistellung` - Datum

### Sonstiges (auf Employee/Contract Attributes)
- `Logis` - Text/Betrag (Unterkunft)
- `Mankogeld` - Betrag (verschlüsselt)
- `Pfaendung` - Betrag/Text (verschlüsselt)
- `Saisonarbeitnehmer` - Ja/Nein
- `Erwerbsminderungsrentner` - Ja/Nein
- `WebZeitPin` - PIN für Zeiterfassung
- `AbweichendePersonalNr` - Alternative Personalnummer
- `Leistungsgruppe` - Text
- `Arbeitnehmergruppe` - Text
- `Gruppename` - Text
- `MitgliedsbescheinigungVorhanden` - Ja/Nein
- `DauerhaftesAusblenden` - Ja/Nein
- `Konzerneintritt` - Datum
- `DienstplanKommtZeitCheck`, `DienstplanGehtZeitCheck` - Ja/Nein
- `Lohnlauf/LohnlaufDurchgefuehrt/LohnscheinAngefordert` - Status
- `SofortmeldungDurchgefuehrt/ExternDurchgefuehrt/RelevantenFelderGesetzt` - Status
- `AndereDringende/AndereDringendeWert` - Text
- `BefristungsBeschluss/BefristungsVerlaengerungsBeschluss` - Text
- `AbweichendesSteuerbrutto` - Betrag (verschlüsselt)
- `AbweichenderUeberstundenUebertragVorjahr` - Stunden
- `AbweichenderGuttageUebertragVorjahr` - Tage
- `AbweichendesUebernommenesUrlaubsgeldVorjahr` - Betrag
- `AbweichenderUrlaubVomVorjahr` - Tage
- `UeberstundenUebertragVorjahrAutomatisch` - Ja/Nein
- `GuttageUebertragVorjahrAutomatisch` - Ja/Nein
- `UebernommenesUrlaubsgeldAutomatisch` - Ja/Nein
- `UrlaubVomVorjahrAutomatisch` - Ja/Nein
- `SchulabschlussID`, `AusbildungsID` - Lookup-IDs
- `Taetigkeitsschluessel` - Code
- `StundenlohnSfn`, `StundenlohnEfz` - Varianten des Stundenlohns
- `StundenlohnSchichten` - Stundenlohn pro Schicht
- `Kostentraeger` - Text
- `AbfuehrungAVRV` - Text
- `AuslaendischesArbeitsentgelt` - Betrag (verschlüsselt)
- `RenteVersorungsbezuege` - Betrag (verschlüsselt)
- `Ausbildungsverguetung` - Betrag (verschlüsselt)
- `Vorruhestandverguetung` - Betrag (verschlüsselt)
- `ZuInkludierendeMehrarbeitsStd` - Stunden
- `AbweichendeZuInkludierendeMehrarbeitsStd` - Stunden
- `AbweichendeSollzeit` - Stunden
- `SollzeitEntsprichtIstzeit` - Ja/Nein
- `SollzeitMitGruppenwerte` - Ja/Nein
- `KrankenkasseOrt` - Ort
- `PrivateKrankenversicherungName` - Name
- `Bundesland`, `BundeslandEinsatzgebiet` - Text

---

## 📋 Empfohlene Implementierung

### Phase 1 - KRITISCH (sofort)
1. **Notfallkontakt** → `hcm_employees`: `emergency_contact_name`, `emergency_contact_phone`
2. **Dienstwagen** → `hcm_employee_contracts`: `company_car_enabled` (boolean), `travel_cost_reimbursement` (TEXT, verschlüsselt)
3. **Zusätzliche Personaldaten** → `hcm_employees`: `birth_surname`, `birth_place`, `birth_country`, `title`, `name_prefix`
4. **Befristung/Probezeit** → `hcm_employee_contracts`: `is_fixed_term` (boolean), `fixed_term_end_date`, `probation_end_date`, `employment_relationship_type`
5. **Arbeitserlaubnis** → `hcm_employees`: `permanent_residence_permit` (boolean), `work_permit_until` (date), `border_worker_country`
6. **Behinderung Details** → `hcm_employees`: `disability_id_number`, `disability_id_valid_from`, `disability_id_valid_until`, `disability_office`, `disability_office_location`; `hcm_employee_contracts`: `additional_vacation_disability` (Tage)

### Phase 2 - WICHTIG (bald)
7. **Arbeitsort/Standort** → `hcm_employee_contracts`: `work_location_name`, `work_location_address`, `work_location_postal_code`, `work_location_city`, `work_location_state`, `branch_name`, `department_id` (FK)
8. **Vorgesetzter** → `hcm_employees`: `supervisor_id` (FK zu HcmEmployee), `deputy_id` (FK), `alias`
9. **Schulungen** → `hcm_employees`: `hygiene_training_date`; `hcm_employee_contracts`: `parent_eligibility_proof_date`
10. **RV Details** → `hcm_employee_contracts`: `pension_insurance_company_number`, `pension_insurance_exempt` (boolean)
11. **Zusätzliche Beschäftigung** → `hcm_employee_contracts` attributes (JSON)
12. **E-Mail Geschäftlich** → `hcm_employees`: `business_email`

### Phase 3 - NICE-TO-HAVE (später)
- Alle anderen Felder in `attributes` JSON-Spalte ablegen

