<?php
  $info = $_REQUEST['info'];
  $cb = $_REQUEST['cb'];

  $opts = array(
    'http'=>array(
      'method' => 'PUT',
      'header'  => "Content-type: text/plain\r\n",
      'content' => $info
    )
  );
  $context=stream_context_create($opts);
  file_get_contents($cb,false,$context);
  exit;
?>


