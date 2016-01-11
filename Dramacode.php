<?php
/**
Génère les formats détachés et le site statique basique sur Dramacode
 */
// cli usage
Dramacode::deps();
set_time_limit(-1);
if (realpath($_SERVER['SCRIPT_FILENAME']) != realpath(__FILE__)) {
  // file is include do nothing
}
else if (php_sapi_name() == "cli") {
  Dramacode::cli();
}
class Dramacode 
{
  static $sets = array(
    "moliere" => array(
      "glob" => '../moliere/*.xml', 
      "publisher" => 'OBVIL, projet Molière',
      "identifier" => "http://obvil.paris-sorbonne.fr/corpus/moliere/%s",
      "source" => "http://dramacode.github.io/moliere/%s.xml",
    ),
    "bibdramatique" => array(
      "glob" => '../bibdramatique/*.xml', 
      "publisher" => "CELLF, Bibliothèque dramatique", 
      "identifier" => "http://bibdramatique.paris-sorbonne.fr/%s",
      "source" => "http://dramacode.github.io/bibdramatique/%s.xml",
    ),
    "racine" => array(
      "glob"=>'../racine/*.xml', 
      "publisher"=>"Dramacode", 
      // "identifier" => "http://dramacode.github.io/racine/%s",
      "source" => "http://dramacode.github.io/racine/%s.xml",
    ),
    "corneille-pierre" => array(
      "glob" => '../corneille-pierre/*.xml', 
      "publisher" => "Dramacode", 
      // "identifier" => "http://dramacode.github.io/corneille-pierre/%s",
      "source" => "http://dramacode.github.io/corneille-pierre/%s.xml",
    ),
    "divers" => array(
      "glob" => '../divers/*.xml', 
      "publisher" => "Dramacode", 
      // "identifier" => "http://dramacode.github.io/divers/%s",
      "source" => "http://dramacode.github.io/divers/%s.xml",
    ),
    "quinault" => array(
      "glob"=> '../quinault/*.xml', 
      "publisher" => "Dramacode", 
      // "identifier" => "http://dramacode.github.io/quinault/%s",
      "source" => "http://dramacode.github.io/quinault/%s.xml",
    ),
    "regnard" => array(
      "glob" => '../regnard/*.xml', 
      "publisher" => "Dramacode", 
      // "identifier" => "http://dramacode.github.io/regnard/%s",
      "source" => "http://dramacode.github.io/regnard/%s.xml",
    ),
    "scarron" => array(
      "glob" => '../scarron/*.xml', 
      "publisher" => "Dramacode", 
      // "identifier" => "http://dramacode.github.io/scarron/%s",
      "source" => "http://dramacode.github.io/scarron/%s.xml",
    ),
  );
  static $formats = array(
    'epub' => '.epub',
    'kindle' => '.mobi',
    'markdown' => '.md',
    'iramuteq' => '.txt',
    'html' => '.html',
    // 'docx' => '.docx',
  );
  /** petite base sqlite pour conserver la mémoire des doublons etc */
  static $create = "
PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;

CREATE TABLE play (
  -- une pièce
  id         INTEGER, -- rowid auto
  code       TEXT,    -- nom de fichier sans extension
  filemtime  INTEGER, -- date de dernière modification du fichier pour update
  publisher  TEXT,    -- nom de l’institution qui publie
  identifier TEXT,    -- uri on publisher
  source     TEXT,    -- XML TEI refercenced URI
  author     TEXT,    -- auteur
  title      TEXT,    -- titre
  year       INTEGER, -- année, généralement la publication papier est la seule date sûre
  acts       INTEGER, -- nombre d’actes, essentiellement 5, 3, 1 ; ajuster pour les prologues
  verse      BOOLEAN, -- uniquement si majoritairement en vers, ne pas cocher si chanson mêlée à de la prose
  genrecode  TEXT,    -- comedy|tragedy
  genre      TEXT,    -- genre tel que dans le titre “Comédie héroïque avec danse et musique”
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX play_code ON play(code);
CREATE INDEX play_author_year ON play(author, year, title);
CREATE INDEX play_year_author ON play(year, author, title);

  ";
  /** Lien à une base SQLite */
  public $pdo;
  /** Requête d’insertion d’une pièce */
  private $_insert;
  /** Pièce XML/TEI en cours de traitement */
  private $_dom;
  /** Processeur xpath */
  private $_xpath;
  /** Processeur xslt */
  private $_xslt;
  /** Vrai si dépendances vérifiées et chargées */
  private static $_deps;
  /** A logger, maybe a stream or a callable, used by self::log() */
  private static $_logger;
  /** Log level */
  public static $debug = true;
  /**
   * Constructeur de la base
   */
  public function __construct($sqlitefile, $logger) {
    self::$_logger = $logger;
    $this->connect($sqlitefile);
    // create needed folders 
    foreach (self::$formats as $format => $extension) {
      if (!file_exists($dir = dirname(__FILE__).'/'.$format)) {
        mkdir($dir, 0775, true);
        @chmod($dir, 0775);  // let @, if www-data is not owner but allowed to write
      }
    }
  }
  /**
   * Produire les exports depuis le fichier XML
   */
  public function add($srcfile, $setcode=null, $force=false) {
    $teinte = new Teinte_Doc($srcfile);
    // ici on pourrait s’épargner les frais de renseigner la base en testant la date filemtime
    $this->insert($teinte, $setcode);
    $echo = "";
    foreach (self::$formats as $format => $extension) {
      $destfile = dirname(__FILE__).'/'.$format.'/'.$teinte->filename.$extension;
      if (!$force && file_exists($destfile) && $teinte->filemtime < filemtime($destfile)) continue;
      // delete destfile if exists ?
      if (file_exists($destfile)) unlink($destfile);
      $echo .= " ".$format;
      // TODO git $destfile
      if ($format == 'html') $teinte->html($destfile, 'http://oeuvres.github.io/Teinte/');
      else if ($format == 'md') $teinte->md($destfile);
      else if ($format == 'iramuteq') $teinte->iramuteq($destfile);
      else if ($format == 'epub') {
        $livre = new Livrable_Tei2epub($srcfile, self::$_logger);
        $livre->epub($destfile);
        // transformation auto en kindle
        $cmd = dirname(__FILE__)."/kindlegen ".$destfile;
        $last = exec ($cmd, $output, $status);
        $mobi = dirname(__FILE__).'/'.$format.'/'.$teinte->filename.".mobi";
        // error ?
        if (!file_exists($mobi)) {
          self::log(E_USER_ERROR, "\n".$status."\n".join("\n", $output)."\n".$last."\n");
        }
        else {
          rename( $mobi, dirname(__FILE__).'/kindle/'.$teinte->filename.".mobi");
          $echo .= " kindle";
        }
      }
      else if ($format == 'docx') {
        $echo .= " docx";
        Toff_Tei2docx::docx($srcfile, $destfile);
      }
    }
    if ($echo) self::log(E_USER_NOTICE, $srcfile.$echo);
  }
  /**
   * Insertion de la pièce
   */
  private function insert($teinte, $setcode) {
    // supprimer la pièce, des triggers doivent normalement supprimer la cascade.
    $this->pdo->exec("DELETE FROM play WHERE code = ".$this->pdo->quote($teinte->filename));
    // métadonnées de pièces
    $year = null;
    $verse = null;
    $genrecode = null;
    $genre = null;
    $author = $teinte->xpath->query("/*/tei:teiHeader//tei:author");
    if ($author->length) $author = $author->item(0)->textContent;
    else $author = null;
    $nl = $teinte->xpath->query("/*/tei:teiHeader/tei:profileDesc/tei:creation/tei:date");
    if ($nl->length) {
      $n = $nl->item(0);
      $year = 0 + $n->getAttribute ('when');
      if(!$year) $year = 0 + $n->nodeValue;
    }
    if(!$year) $year = null;
    $title = $teinte->xpath->query("/*/tei:teiHeader//tei:title");
    if ($title->length) $title = $title->item(0)->textContent;
    else $title = null;
    $nl = $teinte->xpath->evaluate("/*/tei:teiHeader//tei:term[@type='genre']");
    if ($nl->length) {
      $n = $nl->item(0);
      $genrecode = $n->getAttribute ('subtype');
      $genre = $n->nodeValue;
    }
    $acts = $teinte->xpath->evaluate("count(/*/tei:text/tei:body//tei:*[@type='act'])");
    if (!$acts) $acts = $teinte->xpath->evaluate("count(/*/tei:text/tei:body/*[tei:div|tei:div2])");
    if (!$acts) $acts = 1;
    $l = $teinte->xpath->evaluate("count(//tei:sp/tei:l)");
    $p = $teinte->xpath->evaluate("count(//tei:sp/tei:p)");
    if ($l > 2*$p) $verse = true;
    else if ($p > 2*$l) $verse = false;
    if (isset(self::$sets[$setcode]['identifier'])) $identifier = sprintf (self::$sets[$setcode]['identifier'], $teinte->filename);
    else $identifier = null;
    $this->_insert->execute(array(
      $teinte->filename,
      $teinte->filemtime,
      self::$sets[$setcode]['publisher'],
      $identifier,
      sprintf (self::$sets[$setcode]['source'], $teinte->filename),
      $author,
      $title,
      $year,
      $acts,
      $verse,
      $genrecode,
      $genre,
    ));
  }
  /**
   * Ligne bibliographique pour une pièce
   */
  public function bibl($play) {
    if (is_string($play)) {
      $playcode = $this->pdo->quote($playcode);
      $play = $this->pdo->query("SELECT * FROM play WHERE code = $playcode")->fetch();
    }
    $bibl = $play['author'].', '.$play['title'].' ('.$play['year'];
    if ($play['genre'] == 'tragedy') $bibl .= ', tragédie';
    else if ($play['genre'] == 'comedy') $bibl .= ', comédie';
    $bibl .= ', '.$play['acts'].(($play['acts']>2)?" actes":" acte");
    $bibl .= ', '.(($play['verse'])?"vers":"prose");
    $bibl .= ')';
    return $bibl;
  }
  /**
   * Sortir le catalogue en table html
   */
  public function table() {
    echo '<table class="sortable">
  <thead>
    <tr>
     <th>Éditeur</th>
     <th>Auteur</th>
     <th>Date</th>
     <th>Titre</th>
     <th>Téléchargements</th>
    </tr>
  </thead>
    ';
    foreach ($this->pdo->query("SELECT * FROM play ORDER BY author, year") as $play) {
      echo "\n    <tr>\n";
      if ($play['identifier']) echo '      <td><a href="'.$play['identifier'].'">'.$play['publisher']."</a></td>\n";
      else echo '      <td>'.$play['publisher']."</td>\n";
      echo '      <td>'.$play['author']."</td>\n";
      echo '      <td>'.$play['year']."</td>\n";
      echo '      <td>'.$play['title'];
      echo ' (';
      if ($play['genre'] == 'tragedy') echo ', tragédie';
      else if ($play['genre'] == 'comedy') echo ', comédie';
      if ($play['acts']) echo ', '.$play['acts'].(($play['acts']>2)?" actes":" acte");
      echo ', '.(($play['verse'])?"vers":"prose");
      echo ")</td>\n";
      echo '      <td>';
      echo '<a href="'.$play['identifier'].'">TEI</a>';
      $sep = ", ";
      foreach ( self::$formats as $label=>$extension) {
        echo $sep.'<a href="'.$label.'/'.$play['code'].$extension.'">'.$label.'</a>';
      }
      echo "</td>\n    </tr>\n";
    }
    echo "\n</table>\n";
  }

  /** 
   * Connexion à la base 
   */
  private function connect($sqlite) {
    $dsn = "sqlite:" . $sqlite;
    // si la base n’existe pas, la créer
    if (!file_exists($sqlite)) { 
      if (!file_exists($dir = dirname($sqlite))) {
        mkdir($dir, 0775, true);
        @chmod($dir, 0775);  // let @, if www-data is not owner but allowed to write
      }
      $this->pdo = new PDO($dsn);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
      @chmod($sqlite, 0775);
      $this->pdo->exec(Dramacode::$create);
    }
    else {
      $this->pdo = new PDO($dsn);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }
    // table temporaire en mémoire
    $this->pdo->exec("PRAGMA temp_store = 2;");
    $this->_insert = $this->pdo->prepare("
    INSERT INTO play (code, filemtime, publisher, identifier, source, author, title, year, acts, verse, genrecode, genre)
              VALUES (?,    ?,         ?,         ?,          ?,      ?,      ?,     ?,    ?,    ?,     ?,         ?);
    ");

  }
  static function deps() {
    if(self::$_deps) return;
    // Deps
    $inc = dirname(__FILE__).'/../Livrable/Tei2epub.php';
    if (!file_exists($inc)) {
      echo "Impossible de trouver ".realpath(dirname(__FILE__).'/../')."/Livrable/
    Vous pouvez le télécharger sur https://github.com/oeuvres/Livrable\n"; 
      exit();
    } 
    else {
      include_once($inc);
    }
    $inc = dirname(__FILE__).'/../Teinte/Doc.php';
    if (!file_exists($inc)) {
      echo "Impossible de trouver ".realpath(dirname(__FILE__).'/../')."/Teinte/
    Vous pouvez le télécharger sur https://github.com/oeuvres/Teinte\n"; 
      exit();
    } 
    else {
      include_once($inc);
    }
    $inc = dirname(__FILE__).'/../Toff/Tei2docx.php';
    if (!file_exists($inc)) {
      echo "Impossible de trouver ".realpath(dirname(__FILE__).'/../')."/Toff/
    Vous pouvez le télécharger sur https://github.com/oeuvres/Toff\n"; 
      exit();
    } 
    else {
      include_once($inc);
    }
    self::$_deps=true;
  }
  /**
   * Custom error handler
   * May be used for xsl:message coming from transform()
   * To avoid Apache time limit, php could output some bytes during long transformations
   */
  static function log( $errno, $errstr=null, $errfile=null, $errline=null, $errcontext=null) {
    $errstr=preg_replace("/XSLTProcessor::transform[^:]*:/", "", $errstr, -1, $count);
    if ($count) { // is an XSLT error or an XSLT message, reformat here
      if(strpos($errstr, 'error')!== false) return false;
      else if ($errno == E_WARNING) $errno = E_USER_WARNING;
    } 
    // not a user message, let work default handler
    else if ($errno != E_USER_ERROR && $errno != E_USER_WARNING && $errno != E_USER_NOTICE ) return false;
    // a debug message in normal mode, do nothing
    if ($errno == E_USER_NOTICE && !self::$debug) return true;
    if (!self::$_logger);
    else if (is_resource(self::$_logger)) fwrite(self::$_logger, $errstr."\n");
    else if ( is_string(self::$_logger) && function_exists(self::$_logger)) call_user_func(self::$_logger, $errstr);
  }
  /**
   * Command line API 
   */
  static function cli() {
    $timeStart = microtime(true);
    $usage = "\n usage    : php -f ".basename(__FILE__)." base.sqlite set\n";
    array_shift($_SERVER['argv']); // shift first arg, the script filepath
    
    // pas d’argument, on démarre sur les valeurs par défaut
    if (!count($_SERVER['argv'])) {
      $base = new Dramacode('dramacode.sqlite', STDERR);
      foreach(self::$sets as $setcode=>$setrow) {
        $glob = $setrow['glob'];
        foreach(glob($glob) as $file) {
          $base->add($file, $setcode);
        }
      }
      exit();
    }
    // des arguments, on joue plus fin
    $sqlite = array_shift($_SERVER['argv']);
    $base = new Dramacode($sqlite,  STDERR);
    if (!count($_SERVER['argv'])) exit("\n    Quel set insérer ?\n");
    $setcode = array_shift($_SERVER['argv']);
    foreach(glob(self::$sets[$setcode]['glob']) as $file) {
      $base->add($file, $setcode);
    }
  }
}
?>