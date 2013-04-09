<?php
/**
 * Newsfeed view file
 *
 * @author Vladimir Gerasimov <freelancervip@gmail.com>
 *
 */
Yii::trace('Template loaded at ' .time());
?>
<?php
echo CHtml::openTag('div', $htmlOptions); //open main div
echo '<noscript>Please enable JavaScript in order to use real-time updates</noscript>';
?>
<div class="newsfeed">
  <ul>
    <li class="header">Recent activities</li>
   </ul>
</div>
<?php
echo CHtml::closeTag('div'); //closing main div
?>
<!-- end of YiiNewsfeedRealtime -->