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
        <p>Ce site sur Github expose les textes de l’“organisation” Dramacode. Cette page est générée automatiquement pour fournir une liste de liens vers des formats d’export pour la lecture (epub, mobi), mais aussi la recherche (markdown, iramuteq), et surtout les sources XML/TEI.</p>
        <?php 
include('build.php');
$base = new Dramacode('dramacode.sqlite');
$base->table();
        ?>
      </article>
    <script src="../Teinte/Sortable.js">//</script>
  </body>
</html>
