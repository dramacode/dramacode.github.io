<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8" />
    <title>Dramacode</title>
    <link rel="stylesheet" type="text/css" href="../Teinte/tei2html.css" />
  </head>
  <body>
      <article id="article">
        <h1><a href="http://dramacode.github.io/">Dramacode</a>, textes de théâtre en libre accès</h1>
        <p>Ce site sur Github expose les textes de la communauté Dramacode. Cette page est générée automatiquement pour fournir une liste de liens sur les sources XML/TEI, ainsi que des formats d’export pour la lecture (epub, mobi) et pour la recherche (markdown, iramuteq).</p>
        <?php 
include('Dramacode.php');
$base = new Dramacode('dramacode.sqlite');
$base->table();
        ?>
      </article>
    <script src="../Teinte/Sortable.js">//</script>
  </body>
</html>
