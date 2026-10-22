-- Campagne « Saison 2027 » : invitation aux clubs à publier leurs événements.
-- Le corps contient les placeholders {club} {contact} {lieu} {type}
-- remplacés à l'envoi (voir api/admin/mailing/send.php).
-- Exécution : phpMyAdmin ou `mysql cool5792_sports_events < ce fichier`.

INSERT INTO mailing_campaigns (subject, body) VALUES (
    '{club} : faites connaître vos randos 2027 sur rando.partageonslaforet.be',
    'Bonjour,

Chaque année, {club} organise des événements {type} à {lieu} — et nous aimerions que le calendrier 2027 en profite.

Le site rando.partageonslaforet.be existe ! C''est un calendrier en ligne des randonnées organisées en Wallonie : VTT, cyclos, marches, gravel… Il fait partie de partageonslaforet.be, une initiative privée portée par quelques bénévoles passionnés de forêt et de plein air. Pas de société commerciale, pas de commission : juste un outil gratuit pensé pour les clubs et les marcheurs.

Publier vos événements prend quelques minutes :

1. Créez votre compte sur rando.partageonslaforet.be (bouton « Créer un compte »).
2. Complétez votre profil organisateur : nom du club, logo, coordonnées.
3. Publiez un premier événement : date, lieu, distance, prix, lien d''inscription…

Vos événements restent sous votre contrôle : vous pouvez les modifier ou les retirer à tout moment, et les dupliquer d''une année sur l''autre.

En les publiant ici, ils deviennent visibles gratuitement auprès de tous les randonneurs qui consultent le calendrier — un vrai plus pour vos inscriptions.

Une question, un souci lors de l''inscription ? Répondez simplement à cet email, nous vous aiderons avec plaisir.

Sportivement,

L''équipe bénévole de Partageons la Forêt
https://rando.partageonslaforet.be'
);
