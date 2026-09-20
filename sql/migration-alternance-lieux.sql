-- Le rythme ne connaissait que l'école et l'entreprise. Une semaine de congés,
-- un jour férié ou une absence ne sont ni l'une ni l'autre : les compter comme
-- des jours d'entreprise fausserait le bilan et le journal.

ALTER TABLE `alternance_periodes`
  MODIFY `lieu` ENUM('ecole', 'entreprise', 'conges', 'ferie', 'absence') NOT NULL;
