<?php
include('build.php');
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title>Dramacode</title>
  </head>
  <body>
      <article id="article">
        <h1><a href="http://dramacode.github.io/">Dramacode</a>, le catatalogue, textes de théâtre en libre accès</h1>
        <p>Ce site hébergé sur Github permet d’exposer les textes libres de la communauté “Dramacode”, des personnes intéressées par l’étude informatisée du Théâtre classique. Les fichiers sources sont en XML/TEI, et maintenus comme des projets de code sur <a href="http://dramacode.github.io/">Github</a>. Cette page est générée automatiquement à destination des chercheurs qui ont besoin d’un point d’accès rapide pour télécharger un texte dans un format ou un autre, ce n’est le site officiel d’aucun des partenaires dramacode. Retrouvez un lien sur l’édition dans son contexte éditorial dans la colonne Éditeur.</p>
        <?php
$base = new Dramacode('dramacode.sqlite');
$base->table();
        ?>
      </article>
    <script src="../Teinte/Sortable.js">//</script>
  </body>
</html>
