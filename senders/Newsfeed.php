<?php
/**
 * Newsfeed class file.
 *
 * Relies on yii-redis component @link http://www.yiiframework.com/extension/yii-redis
 *
 * @author Vladimir Gerasimov <freelancervip@gmail.com>
 *
 */
class Newsfeed
{
  //some constant values
  const WANT =          'want';
  const HAS =           'has';
  const COMMENT =       'commented';
  const ALSOCOMMENT =   'alsocommented';

  const CHANNEL =       'newsfeed';


  /**
   * When someone wants user's discovery
   *
   * @static
   * @param int $whoid Who wants this product
   * @param string $whoname Its name
   * @param string $whouserpic Its avatar
   * @param int $product_owner Product owner
   * @param int $product_id ID of product
   * @param string $product_name product title (name)
   * @param string $wanted_date Timestamp of 'want' action
   *
   * @see wanthas
   */
  public static function want($whoid, $whoname, $whouserpic, $product_owner, $product_id, $product_name, $wanted_date){
    self::wanthas(self::WANT, $whoid, $whoname, $whouserpic, $product_owner, $product_id, $product_name, $wanted_date);
  }
  /**
   * When someone has user's discovery
   *
   * @static
   * @param int $whoid Who has this product
   * @param string $whoname Its name
   * @param string $whouserpic Its avatar
   * @param int $product_owner Product owner
   * @param int $product_id ID of product
   * @param string $product_name Product title (name)
   * @param string $has_date Timestamp of 'has' action
   *
   *  @see wanthas
   */
  public static function has($whoid, $whoname, $whouserpic, $product_owner, $product_id, $product_name, $has_date){
    self::wanthas(self::HAS, $whoid, $whoname, $whouserpic, $product_owner, $product_id, $product_name, $has_date);
  }
  /**
   * Publishes 'vote' message to channel
   *
   * @param int $type Type of vote. Want or has
   * @param int $whoid Who wants or has this product
   * @param string $whoname Its name
   * @param string $whouserpic Its avatar
   * @param int $target Product owner
   * @param int $product_id Product ID
   * @param string $product_name Product name
   * @param int $date Action timestamp
   */
  protected function wanthas($type, $whoid, $whoname, $whouserpic, $target, $product_id, $product_name, $date){
    $data = array(
      'event' => 'vote',
      'eventData' => array(
        'type'=>$type,
        'timestamp'=>$date,
        'voter'=>array('id'=>$whoid, 'name'=>$whoname, 'userpic'=>$whouserpic),
        'target'=>array('id'=>$target),
        'product'=>array('id'=>$product_id, 'title'=>$product_name),
      ),
    );
    //saving activity
    self::saveLastActivity($target, $data);
    //publishing message to owner
    self::publishToChannel($target, $data);
  }
  /**
   * When someone follows user
   *
   * @static
   * @param int $whoid Follower ID
   * @param string $whoname Its name
   * @param string $whouserpic Its avatar
   * @param int $following Following ID
   * @param int $follow_date Timestamp of date added
   */
  public static function follow($whoid, $whoname, $whouserpic, $following, $follow_date){
    $data = array(
      'event' => 'follow',
      'eventData' => array(
        'type'=>'follow',
        'timestamp'=>$follow_date,
        'follower'=>array('id'=>$whoid, 'name'=>$whoname, 'userpic'=>$whouserpic),
        'following'=>array('id'=>$following),
      ),
    );
    //saving activity
    self::saveLastActivity($following, $data);
    //publishing message to owner
    self::publishToChannel($following, $data);
  }
  /**
   * When someone comments on user's discovery
   *
   * @static
   * @param int $whoid Commentator ID
   * @param string $whoname Commentator name
   * @param string $whouserpic Commentator avatar
   * @param int $target Who receive the message
   * @param int $productid Product ID
   * @param string $product_name Product title
   * @param int $comment_added Timestamp of comment added
   *
   * @see comment
   */
  public static function commented($whoid, $whoname, $whouserpic, $target, $productid, $product_name, $comment_added){
   self::comment(self::COMMENT, $whoid, $whoname, $whouserpic, $target, $productid, $product_name, $comment_added);
  }
  /**
   * When someone comments on a product I commented
   *
   * @static
   * @param int $whoid Commentator ID
   * @param string $whoname Commentator name
   * @param string $whouserpic Commentator avatar
   * @param int $target Who receive the message
   * @param int $productid Product ID
   * @param string $product_name Product title
   * @param int $comment_added Timestamp of comment added
   *
   * @see comment
   */
  public static function alsocommented($whoid, $whoname, $whouserpic, $target, $productid, $product_name, $comment_added){
    self::comment(self::ALSOCOMMENT, $whoid, $whoname, $whouserpic, $target, $productid, $product_name, $comment_added);
  }
  /**
   * Publish 'comment' event
   *
   * @param string $type Event type. commented or alsocommented
   * @param int $whoid Commentator ID
   * @param string $whoname Commentator name
   * @param string $whouserpic Commentator avatar
   * @param int $target Who receive the message
   * @param int $productid Product ID
   * @param string $product_name Product title
   * @param int $comment_added Timestamp of comment added
   */
  protected function comment($type, $whoid, $whoname, $whouserpic, $target, $productid, $product_name, $comment_added){
    $data = array(
      'event' => 'comment',
      'eventData' => array(
        'type'=>$type,
        'timestamp'=>$comment_added,
        'commentator'=>array('id'=>$whoid, 'name'=>$whoname, 'userpic'=>$whouserpic),
        'target'=>array('id'=>$target),
        'product'=>array('id'=>$productid, 'title'=>$product_name),
      ),
    );
    //saving activity
    self::saveLastActivity($target, $data);
    //publishing message to owner
    self::publishToChannel($target, $data);
  }
  /**
   * Saves last activity for user
   *
   * @param int $uid User ID
   * @param array $data Array of activity
   */
  protected function saveLastActivity($uid, $data){
    $lastactivity = 'uid:'.$uid.':lastactivity';
    $list = new ARedisList($lastactivity);
    $list->unshift(CJSON::encode($data));
    /**
     * Save last activities
     *
     * It is important to note that when used in this way LTRIM is an O(1)
     * operation because in the average case just one element is removed from the tail of the list
     */
    $list->trim(0, Yii::app()->params['socket']['last_activity_count'] - 1);
  }
  /**
   * Publishes message to  channel
   *
   * @param string $uid Channel name
   * @param array $data Message array
   */
  protected function publishToChannel($uid, $data){
    $channel = 'uid:'.$uid.':channels:'.self::CHANNEL;
    $publisher = new ARedisChannel($channel);
    $publisher->publish(CJSON::encode($data));
  }

}