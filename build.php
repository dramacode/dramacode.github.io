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
  const FORCE = true;
  static $globs = array(
    '../moliere/*.xml',
    '../racine/*.xml',
    '../corneille-pierre/*.xml',
    '../bibdramatique/*.xml',
    '../divers/*.xml',
    '../quinault/*.xml',
    '../regnard/*.xml',
    '../scarron/*.xml',
  );
  static $formats = array(
    'html' => '.html',
    'md' => '.md',
    'iramuteq' => '.txt',
    'docx' => '.docx',
    'epub' => '.epub',
  );
  /** petite base sqlite pour conserver la mémoire des doublons etc */
  static $create = "
PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;

CREATE TABLE play (
  -- une pièce
  id        INTEGER, -- rowid auto
  code      TEXT,    -- nom de fichier sans extension
  filemtime INTEGER, -- date de dernière modification du fichier pour update
  author    TEXT,    -- auteur
  title     TEXT,    -- titre
  titlesub  TEXT,    -- genre tel que dans le titre “Comédie héroïque avec danse et musique”
  published INTEGER, -- année, reprise du nom de fichier, ou dans le XML
  acts      INTEGER, -- nombre d’actes, essentiellement 5, 3, 1 ; ajuster pour les prologues
  verse     BOOLEAN, -- uniquement si majoritairement en vers, ne pas cocher si chanson mêlée à de la prose
  genre     TEXT,    -- comedy|tragedy
  PRIMARY KEY(id ASC)
);
CREATE UNIQUE INDEX play_code ON play(code);

  ";
  /** Requête d’insertion d’une pièce */
  static $insert = "INSERT OR REPLACE INTO play VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
  /** Lien à une base SQLite */
  public $pdo;
  /** Pièce XML/TEI en cours de traitement */
  private $_dom;
  /** Processeur xpath */
  private $_xpath;
  /** Processeur xslt */
  private $_xslt;
  /** A-t-on vérifié et inclus les dépendances ? */
  private static $_deps;
  /**
   * Constructeur de la base
   */
  public function __construct($sqlitefile) {
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
  public function add($srcfile, $force=false) {
    $teinte = new Teinte_Doc($srcfile);
    $filename = pathinfo($srcfile, PATHINFO_FILENAME);
    $echo = "";
    foreach (self::$formats as $format => $extension) {
      $destfile = dirname(__FILE__).'/'.$format.'/'.$filename.$extension;
      if (!$force && file_exists($destfile) && filemtime($srcfile) < filemtime($destfile)) continue;
      $echo .= " ".basename($destfile);
      // TODO git $destfile
      if ($format == 'html') $teinte->html($destfile, '../../Teinte/');
      else if ($format == 'md') $teinte->md($destfile);
      else if ($format == 'iramuteq') $teinte->iramuteq($destfile);
      else if ($format == 'epub') {
        $livre = new Livrable_Tei2epub($srcfile, STDERR);
        $livre->epub($destfile);
      }
      else if ($format == 'docx') {
        Toff_Tei2docx::docx($srcfile, $destfile);
      }
    }
    // TODO, mieux loguer
    if ($echo) echo $srcfile.$echo."\n";
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
   * Command line API 
   */
  static function cli() {
    $timeStart = microtime(true);
    $usage = "\n usage    : php -f ".basename(__FILE__)." base.sqlite *.xml\n";
    array_shift($_SERVER['argv']); // shift first arg, the script filepath
    
    // pas d’argument, on démarre sur les valeurs par défaut
    if (!count($_SERVER['argv'])) {
      $base = new Dramacode('dramabase.sqlite');
      foreach(self::$globs as $glob) {
        foreach(glob($glob) as $file) {
          $base->add($file);
        }
      }
      exit();
    }
    // des arguments, on joue plus fin
    $sqlite = array_shift($_SERVER['argv']);
    $base = new Dramacode($sqlite);
    if (!count($_SERVER['argv'])) exit("\n    Quelles pièces XML/TEI éer ?\n");
    $glob = array_shift($_SERVER['argv']);
    foreach(glob($glob) as $file) {
      $base->add($file);
    }
  }
}
?>