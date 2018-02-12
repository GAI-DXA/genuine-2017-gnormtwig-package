<?php

namespace Gnormtwig;

class Gnormtwig {

  // The location of the Gnorm root.
  protected $baseDir;
  // The location to save built files.
  protected $buildDir;
  protected $buildPath;
  // The the global json data.
  protected $globalJson;
  // If this is a build or not.
  protected $isBuild;
  // The location of the json data files.
  protected $jsonPath;
  // The namespaces and their locations.
  protected $namespaces;
  // The location of the application directory.
  protected $sourceDir;
  protected $sourcePath;
  // The pattern to search for source twig files.
  protected $sourcePattern;
  // The twig environment.
  protected $twigEnvironment;
  // The twig loader.
  protected $twigLoader;

  /**
   * Constructor.
   *
   * Available config keys:
   *
   *  baseDir:    The location of the central Gnorm folder.
   *  source:     The location of the source directory (relative to the base).
   *  pattern:    The pattern to use when searching for source twig files
   *              (relative to the base).
   *  dest:       The location to output the built html (relative to the base).
   *  data:       The location of the JSON data directory (relative to the base).
   *  namespaces: An array of namespace => locations to use as namespaces.
   *  global:     The location of the global JSON File (relative to the base).
   *  isBuild:    Indicates if output is a final build.
   *
   * @param $config
   */
  public function __construct($config) {

    $this->baseDir = $config['baseDir'];
    $this->sourceDir = $config['source'];
    $this->sourcePath = $this->baseDir . '/' . $this->sourceDir;
    $this->sourcePattern = $config['pattern'];
    $this->buildDir = $config['dest'];
    $this->buildPath = $this->baseDir . '/' . $this->buildDir;
    $this->jsonPath = $this->baseDir . '/' . $config['data'];
    $this->namespaces = $config['namespaces'];
    $this->isBuild = $config['isBuild'];

    // Load the global json and set the build flag.
    $this->globalJson = $this->getJson($this->baseDir . '/' . $config['global']);
    $this->globalJson['isBuild'] = $this->isBuild;

  }

  /**
   * Render.
   */
  public function render() {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

    try {
      // Setup the twig environment.
      $twig = $this->getEnvironment();

      // Loop through each file that matches the source pattern.
      foreach ($this->getfiles() as $filename) {
        $this->renderFile($filename, $twig);
      }
    } catch (\Throwable $e) {
      echo $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    }
  }

  /**
   * Get Loader.
   *
   * @return \Twig_Loader_Filesystem
   * @throws \Twig_Error_Loader
   */
  public function getLoader(){
    if (!$this->twigLoader) {
      // Load twig.
      $this->twigLoader = new \Twig_Loader_Filesystem($this->baseDir);

      // Add the namespaces.
      foreach ($this->namespaces as $key => $location) {
        $this->twigLoader->addPath($this->baseDir . '/' . $location, $key);
      }
    }

    return $this->twigLoader;
  }

  /**
   * Get Environment.
   *
   * @return \Twig_Environment'
   */
  public function getEnvironment(){
    if (!$this->twigEnvironment) {
      // Setup the twig environment.
      $this->twigEnvironment = new \Twig_Environment($this->getLoader(), array(
        'cache' => FALSE,
        'debug' => TRUE,
        'autoescape' => FALSE,
      ));
      $this->twigEnvironment->addExtension(new \Twig_Extension_Debug());
    }

    return $this->twigEnvironment;
  }

  /**
   * Get Files.
   *
   * @return array
   */
  protected function getfiles(){
    return glob($this->baseDir . '/' . $this->sourcePattern);
  }

  /**
   * Render Files.
   *
   * @param string $filename
   * @param \Twig_Environment $twig
   */
  protected function renderFile($filename, \Twig_Environment $twig) {
    try {
      // Get the name of the file without extension.
      $basename = basename($filename, '.twig');

      echo "Rendering $basename\n";

      // Get any json.
      $jsonFile = $this->jsonPath . "/{$basename}.json";
      $json = $this->getJson($jsonFile);
      $json = array_merge($json, $this->globalJson);

      // Render the twig file.
      $file = $this->sourceDir . "/{$basename}.twig";
      $rendered = $twig->render($file, $json);

      // Write the output file.
      $destination = $this->buildPath . "/{$basename}.html";
      file_put_contents($destination, $rendered);

    } catch (\Twig_Error $e) {
      $context = $e->getSourceContext();
      echo $e->getRawMessage() . "\n"
        . $context->getName() . " line: " . $e->getLine()
        . "\n" . $context->getCode() . "\n";
    } catch (\Throwable $e) {
      echo $e->getMessage() . "\n";
      $this->outputTwigTrace($e->getTrace(), $twig);
    }
  }

  /**
   * Get Json file and ensure output is an array.
   *
   * @param $file_path
   * @return array
   */
  protected function getJson($file_path) {
    if (file_exists($file_path)) {
      $json_string = file_get_contents($file_path);
      if ($json = json_decode($json_string, TRUE)) {
        return $json;
      }
      else {
        echo "Invalid JSON: $file_path\n";
        return [];
      }
    }
    else {
      echo "No JSON file: $file_path\n";
      return [];
    }
  }

  /**
   * Get twig file names from error trace.
   *
   * @param $trace
   * @param \Twig_Environment $twig
   */
  protected function outputTwigTrace($trace, \Twig_Environment $twig) {
    echo "Trace:\n";

    foreach ($trace as $item) {
      // Get the twig template name from the hashed class name.
      if (isset($item['class']) && strpos($item['class'], '__TwigTemplate_') === 0) {
        $template = new $item['class']($twig);
        echo '  ' . $template->getTemplateName() . "\n";
      }
    }
  }
}
