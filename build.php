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
    "tc" => array(
      "glob" => '
        ../theatre-classique/BOISROBERT_*.xml
        ../theatre-classique/BOURSAULT_*.xml
        ../theatre-classique/CORNEILLEP_*.xml
        ../theatre-classique/CYRANO_*.xml
        ../theatre-classique/GILLET_*.xml
        ../theatre-classique/RACINE*.xml
        ../theatre-classique/ROTROU_*.xml
        ../theatre-classique/SCARRON_*.xml
        ../theatre-classique/VILLIERS_*.xml
        ../theatre-classique/DONNEAUDEVISE_*.xml
      ',
      "publisher" => "Théâtre Classique",
      "identifier" => "http://theatre-classique.fr/pages/programmes/edition.php?t=../documents/%s.xml",
      "source" => "http://dramacode.github.io/theatre-classique/%s.xml",
      "predir" => 'tc-',
    ),
    /*
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
    */
  );
  static $formats = array(
    'markdown' => '.md',
    'iramuteq' => '.txt',
    'html' => '.html',
    'article' => '.html',
    'epub' => '.epub',
    'kindle' => '.mobi',
    // 'docx' => '.docx',
  );
  /** petite base sqlite pour conserver la mémoire des doublons etc */
  static $create = "
PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;

CREATE TABLE play (
  -- une pièce
  id         INTEGER, -- rowid auto
  setcode    TEXT,    -- code de set
  code       TEXT,    -- nom de fichier sans extension
  filemtime  INTEGER, -- date de dernière modification du fichier pour update
  publisher  TEXT,    -- nom de l’institution qui publie
  identifier TEXT,    -- uri on publisher
  source     TEXT,    -- XML TEI refercenced URI
  author     TEXT,    -- auteur
  title      TEXT,    -- titre
  date       INTEGER, -- année, généralement la publication papier est la seule date sûre
  acts       INTEGER, -- nombre d’actes, essentiellement 5, 3, 1 ; ajuster pour les prologues
  verse      BOOLEAN, -- uniquement si majoritairement en vers, ne pas cocher si chanson mêlée à de la prose
  genrecode  TEXT,    -- comedy|tragedy
  genre      TEXT,    -- genre tel que dans le titre “Comédie héroïque avec danse et musique”
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX play_code ON play(code);
CREATE INDEX play_author_date ON play(author, date, title);
CREATE INDEX play_date_author ON play(date, author, title);
CREATE INDEX play_setcode ON play(setcode);

  ";
  /** Lien à une base SQLite */
  public $pdo;
  /** Requête d’insertion d’une pièce */
  private $_insert;
  /** Test de date d’une pièce */
  private $_sqlmtime;
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
  public function __construct($sqlitefile, $logger="php://output") {
    if (is_string($logger)) $logger = fopen($logger, 'w');
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
   *
   */
  /**
   * Produire les exports depuis le fichier XML
   */
  public function add($srcfile, $setcode=null, $force=false) {
    $set = self::$sets[$setcode];
    if (!isset($set['predir'])) $set['predir'] = ''; // used for Théâtre Classique
    $srcname = pathinfo($srcfile, PATHINFO_FILENAME);
    $srcmtime = filemtime($srcfile);
    $this->_sqlmtime->execute(array($srcname));
    list($basemtime) = $this->_sqlmtime->fetch();
    // TODO optimize on dates
    $teinte = new Teinte_Doc($srcfile);
    // time compared
    if ($basemtime < $srcmtime) {
      $this->insert($teinte, $setcode);
    }
    // Specific Théâtre Classique
    if ($setcode == 'tc') {
      $teinte->pre(dirname(__FILE__).'/tc-norm.xsl');
    }
    $echo = "";
    foreach (self::$formats as $format => $extension) {
      $dir = $set['predir'].$format;
      $destfile = dirname(__FILE__).'/'.$dir.'/'.$srcname.$extension;
      if (!$force && file_exists($destfile) && $srcmtime < filemtime($destfile)) continue;
      if ($format == 'kindle') continue; // kindle mobi should be done just after epub
      // delete destfile if exists ?
      if (file_exists($destfile)) unlink($destfile);
      $echo .= " ".$format;
      // TODO git $destfile
      if ($format == 'html') $teinte->html($destfile, 'http://oeuvres.github.io/Teinte/');
      if ($format == 'article') $teinte->article($destfile);
      else if ($format == 'markdown') $teinte->markdown($destfile);
      else if ($format == 'iramuteq') $teinte->iramuteq($destfile);
      else if ($format == 'epub') {
        $livre = new Livrable_Tei2epub($teinte->dom(), self::$_logger);
        $livre->epub($destfile);
        // transformation auto en mobi, toujours après epub
        $mobifile = dirname(__FILE__).'/'.$set['predir'].'kindle/'.$srcname.".mobi";
        Livrable_Tei2epub::mobi($destfile, $mobifile);
      }
      else if ($format == 'docx') {
        $echo .= " docx";
        Toff_Tei2docx::docx($srcfile, $destfile);
      }
    }
    if ($echo) self::log(E_USER_NOTICE, $srcfile.$echo."\n");
  }
  /**
   * Insertion de la pièce
   */
  private function insert($teinte, $setcode) {
    // supprimer la pièce, des triggers doivent normalement supprimer la cascade.
    $this->pdo->exec("DELETE FROM play WHERE code = ".$this->pdo->quote($teinte->filename()));
    // globa TEI meta
    $meta = $teinte->meta();
    // acts
    $xpath = $teinte->xpath();
    $meta['acts'] = $xpath->evaluate("count(/*/tei:text/tei:body//tei:*[@type='act'])");
    if (!$meta['acts']) $meta['acts'] = $xpath->evaluate("count(/*/tei:text/tei:body/*[tei:div|tei:div2])");
    if (!$meta['acts']) $meta['acts'] = 1;
    // verse
    $l = $xpath->evaluate("count(//tei:sp/tei:l)");
    $p = $xpath->evaluate("count(//tei:sp/tei:p)");
    if ($l > 2*$p) $meta['verse'] = true;
    else if ($p > 2*$l) $meta['verse'] = false;
    else $meta['verse'] = null;
    // genre
    $genre = $genrecode = null;
    $nl = $xpath->evaluate("/*/tei:teiHeader//tei:term[@type='genre']");
    if ($nl->length) {
      $n = $nl->item(0);
      $genrecode = $n->getAttribute ('subtype');
      $genre = $n->nodeValue;
    }


    if (isset(self::$sets[$setcode]['identifier']))
      $identifier = sprintf ( self::$sets[$setcode]['identifier'], $teinte->filename() );
    else $identifier = null;

    $this->_insert->execute(array(
      $setcode,
      $teinte->filename(),
      $teinte->filemtime(),
      self::$sets[$setcode]['publisher'],
      $identifier,
      sprintf ( self::$sets[$setcode]['source'], $teinte->filename() ),
      $meta['author'],
      $meta['title'],
      $meta['date'],
      $meta['acts'],
      $meta['verse'],
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
    $bibl = $play['author'].', '.$play['title'].' ('.$play['date'];
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
      <th>N°</th>
      <th>Éditeur</th>
      <th>Auteur</th>
      <th>Date</th>
      <th>Titre</th>
      <th>Genre</th>
      <th>Actes</th>
      <th>Vers</th>
      <th>Téléchargements</th>
    </tr>
  </thead>
    ';
    $i = 1;
    foreach ($this->pdo->query("SELECT * FROM play ORDER BY author, date") as $play) {
      $set = self::$sets[$play['setcode']];
      echo "\n    <tr>\n";
      echo "      <td>$i</td>\n";
      if ($play['identifier']) echo '      <td><a href="'.$play['identifier'].'">'.$play['publisher']."</a></td>\n";
      else echo '      <td>'.$play['publisher']."</td>\n";
      echo '      <td>'.$play['author']."</td>\n";
      echo '      <td>'.$play['date']."</td>\n";
      if ($play['identifier']) echo '      <td><a href="'.$play['identifier'].'">'.$play['title']."</a></td>\n";
      else echo '      <td>'.$play['title']."</td>\n";
      echo '      <td>';
      if ($play['genrecode'] == 'tragedy') echo 'Tragédie';
      else if ($play['genrecode'] == 'comedy') echo 'Comédie';
      else echo $play['genre'];
      echo "      </td>\n";
      echo "      <td>".$play['acts']."</td>\n";
      echo "      <td>".(($play['verse'])?"vers":"prose")."</td>\n";
      // downloads
      echo '      <td>';
      if ($play['source']) echo '<a href="'.$play['source'].'">TEI</a>';
      $sep = ", ";
      foreach ( self::$formats as $label=>$extension) {
        if ($label == 'article') continue;
        if (isset($set['predir'])) $dir = $set['predir'].$label;
        else $dir = $label;
        echo $sep.'<a href="'.$dir.'/'.$play['code'].$extension.'">'.$label.'</a>';
      }
      echo "      </td>\n";
      echo "    </tr>\n";
      $i++;
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
    INSERT INTO play (setcode, code, filemtime, publisher, identifier, source, author, title, date, acts, verse, genrecode, genre)
              VALUES (?,       ?,    ?,         ?,         ?,          ?,      ?,      ?,     ?,    ?,    ?,     ?,         ?);
    ");
    $this->_sqlmtime = $this->pdo->prepare("SELECT filemtime FROM play WHERE code = ?");
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
    else if (is_resource(self::$_logger)) fwrite(self::$_logger, $errstr);
    else if ( is_string(self::$_logger) && function_exists(self::$_logger)) call_user_func(self::$_logger, $errstr);
  }
  static function epubcheck($glob) {
    echo "epubcheck epub/*.epub\n";
    foreach(glob($glob) as $file) {
      echo $file;
      // validation
      $cmd = "java -jar ".dirname(__FILE__)."/epubcheck/epubcheck.jar ".$file;
      $last = exec ($cmd, $output, $status);
      echo ' '.$status."\n";
      if ($status) rename($file, dirname($file).'/_'.basename($file));
    }
  }
  /**
   * Command line API
   */
  static function cli() {
    $timeStart = microtime(true);
    $usage = "\n usage    : php -f ".basename(__FILE__)." base.sqlite set\n";
    array_shift($_SERVER['argv']); // shift first arg, the script filepath
    $sqlite = 'dramacode.sqlite';
    // pas d’argument, on démarre sur les valeurs par défaut
    if (!count($_SERVER['argv'])) {
      $base = new Dramacode($sqlite, STDERR);
      foreach(self::$sets as $setcode=>$setrow) {
        foreach(preg_split('@\s+@', $setrow['glob']) as $glob) {
          foreach(glob($glob) as $file) {
            $base->add($file, $setcode);
          }
        }
      }
      exit();
    }
    if ($_SERVER['argv'][0] == 'epubcheck') {
      Dramacode::epubcheck('epub/*.epub');
      exit();
    }
    // des arguments, on joue plus fin
    $base = new Dramacode($sqlite,  STDERR);
    if (!count($_SERVER['argv'])) exit("\n    Quel set insérer ?\n");
    $setcode = array_shift($_SERVER['argv']);
    foreach(split(" ", self::$sets[$setcode]['glob']) as $glob) {
      foreach(glob($glob) as $file) {
        $base->add($file, $setcode);
      }
    }
  }
}
?>
