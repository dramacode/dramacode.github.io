<?php
include_once('build.php');
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title>Dramacode</title>
    <base target="_blank"/>
  </head>
  <body>
      <article id="article">
        <h1><a href="https://github.com/dramacode/">Dramacode</a>, le catalogue, textes de théâtre en libre accès</h1>
        <p>Ce site hébergé sur Github permet d’exposer les textes libres de la communauté “Dramacode” (des personnes intéressées par l’étude informatisée du Théâtre classique). Les fichiers sources sont en XML/TEI, et maintenus comme des projets de code sur <a href="https://github.com/dramacode/">Github</a>. Cette page est générée automatiquement à destination des chercheurs qui ont besoin d’un point d’accès rapide pour télécharger un texte dans un format ou un autre, mais nous vous invitons surtout à retrouver  l’édition sur le site du partenaire, en cliquant le lien dans la colonne Éditeur.</p>
        <?php
$base = new Dramacode('dramacode.sqlite');
$base->table();
        ?>
      </article>
    <script src="http://oeuvres.github.io/Teinte/Sortable.js">//</script>
  </body>
</html>
