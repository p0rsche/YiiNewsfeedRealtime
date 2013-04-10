<?php
/**
 * NewsfeedWidget class file
 *
 * @author Vladimir Gerasimov <freelancervip@gmail.com>
 *
 */

/*
 * Usage
 * ----
  $this->Widget('ext.YiiNewsfeedRealtime.NewsfeedWidget', array(
    'config'=>array(
      'host'=>'http://nodeserver', //don't include trailing slash
      'port'=>'2206',
      'channels'=>array('newsfeed', 'dashboard'),
    ),
    'htmlOptions'=>array(),
  ));
 * ----
*/
class NewsfeedWidget extends CWidget
{
  /**
   * @var array Newsfeed config
   */
  public $config = array();

  public $salt = 'th!3!3SpARTa!';
  /**
   * @var array Default config values
   */
  private $defaultConfig = array(
    'host'=> 'localhost',
    'port'=>'2206',
    'channels'=>array(),
    'media'=>'screen', //media type of CSS
    'siteUrl'=>'http://your_url',
    'history_count' => 5,
    'debug' => YII_DEBUG
  );
  public $htmlOptions = array();
  /**
   * Renders the widget
   *
   * @see registerScriptsAndStyles
   */
  public function run(){
    $id = $this->getId();
    $this->htmlOptions['id'] = __CLASS__.'_'.$id;

    // check if options parameter is a json string
    if(is_string($this->config)) {
      if(!($this->config = CJSON::decode($this->config)))
        trigger_error('The config parameter is not a valid JSON string.');
    }
    // merge options with default values
    $this->config = CMap::mergeArray($this->defaultConfig, $this->config);
    $socketOptions = CJavaScript::encode($this->config);
    /**
     * Hashing id + salt for sending to server
     * Be careful - salt string should equal both Yii params and node.js config
     */
    $hash = CJavaScript::encode(md5($this->config['uid'] . $this->salt));
    $this->attachEventHandlers();
    $this->registerScriptsAndStyles(__CLASS__ . '#' . $id , "(new CSocket($socketOptions, $hash)).run();");

    $this->render('newsfeed', array('htmlOptions'=>$this->htmlOptions));
  }
  /**
   * Publishes and registers necessary script files.
   *
   * @param string $id ID of the script to be inserted into the page
   * @param string $embeddedScript Embedded script to be inserted into the page
   */
  protected function registerScriptsAndStyles($id, $embeddedScript){
    $basePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR;
    $baseUrl = Yii::app()->getAssetManager()->publish($basePath, true, -1, YII_DEBUG);
    //@TODO don't forget to minify JS and CSS files for production
    $newsfeed_js = '/js/newsfeed.js';
    $newsfeed_css = '/css/newsfeed.min.css';
    $remoteSocket = $this->config['host'] . ':' . $this->config['port'] . '/socket.io/socket.io.js';
    $cs = Yii::app()->clientScript;
    //register styles
    $cs->registerCssFile(Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext').'/YiiNewsfeedRealtime/assets/'.$newsfeed_css));
    //register scripts
    $cs->registerCoreScript('jquery');
    $cs->registerScriptFile($remoteSocket);
    $cs->registerScriptFile($baseUrl . $newsfeed_js);
    $cs->registerScript($id, $embeddedScript, CClientScript::POS_READY);

  }
  //@TODO attaching event handlers instead of dealing with controller actions
  protected function attachEventHandlers(){
  }
}
