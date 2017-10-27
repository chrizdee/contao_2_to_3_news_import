<?php

error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));

function clean ($text)
{
	return str_replace('', '', $text);
}

if (	$_POST['source_host'] && 
		$_POST['source_user'] && 
		$_POST['source_password'] &&
		$_POST['source_database'] &&
		$_POST['source_pid'] &&
		$_POST['target_host'] && 
		$_POST['target_user'] && 
		$_POST['target_password'] &&
		$_POST['target_database'] &&
		$_POST['target_pid']
		)
{
	// connect to source_database
	$db_source = mysql_connect($_POST['source_host'], $_POST['source_user'], $_POST['source_password']);
	if (!$db_source)
	{
		$messages[] = '<div class="alert alert-error">Can not connect to source database!</div>';
		die;
	}
	mysql_select_db($_POST['source_database'], $db_source);

	// connect to target_database
	$db_target = mysql_connect($_POST['target_host'], $_POST['target_user'], $_POST['target_password'], true);
	if (!$db_target)
	{
		$messages[] = '<div class="alert alert-error">Can not connect to target database!</div>';
		die;
	}
	mysql_select_db($_POST['target_database'], $db_target);

	// get news from source database
	$sql_source = '	SELECT 
						* 
					FROM 
						tl_news 
					WHERE 
						pid='.$_POST['source_pid'];

	$query_source = mysql_query($sql_source, $db_source) or die ($messages[] = '<div class="alert alert-error">MySQL-Error: '.mysql_error().'</div>');

	// insert into contao database
	$num = 1;
	while ($row = mysql_fetch_array($query_source))
	{
		$log[] = 'Get SRC-ID:'.$row['id'].' | Headline: '.$row['headline'];

		if ($row['published']) $published = ', published=1';

		$sql_target = '	INSERT INTO 
					   		tl_news 
					   	SET 
							pid='.$_POST['target_pid'].',
							tstamp='.$row['tstamp'].',
							headline="'.mysql_real_escape_string($row['headline']).'",
							alias="'.$row['alias'].'",
							author=1,
							date='.$row['date'].',
							time='.$row['time'].',
							subheadline="'.mysql_real_escape_string($row['subheadline']).'",
							teaser="'.mysql_real_escape_string($row['teaser']).'",
							source="default",
							cssClass="contao-2"'
							.$published
							;

		// Create content
		$text = $row['text'];

		if ($_POST['import_image'])
		{
			$text = '<div class="main-image"><img src="'.$row['singleSRC'].'" alt="'.$row['alt'].'"></div><div class="news-text">' . $text . '</div>';	
		}				
		$text = str_replace($_POST['source_image_path'], $_POST['target_image_path'], $text);
		$sql_content = 'INSERT INTO 
					   		tl_content 
					   	SET 
							pid={{pid}},
							ptable="tl_news",
							sorting=64,
							tstamp='.$row['tstamp'].',
							type="text",
							text="'.mysql_real_escape_string($text).'"';					

		if ($_POST['testing_mode'] != '1')
		{
			$query_target = mysql_query($sql_target, $db_target) or die ($messages[] = '<div class="alert alert-error">MySQL-Error: '.mysql_error().'</div><div>'.$sql_target.'</div>');
			if ($query_target)
			{
				$log[] = 'Insert SRC-ID:'.$row['id'].' | Headline: '.$row['headline'];
				$num++;
			}

			$sql_content = str_replace('{{pid}}', mysql_insert_id(), $sql_content);

			$query_target = mysql_query($sql_content, $db_target) or die ($messages[] = '<div class="alert alert-error">MySQL-Error: '.mysql_error().'</div><div>'.$sql_content.'</div>');
			if ($query_target)
			{
				$log[] = 'Insert Content SRC-ID:'.$row['id'].' | Headline: '.$row['headline'];
			}
		}
	} // end while

	$log[] = 'SQL-Target: ' . $sql_target;
	$log[] = 'SQL-Content: ' . $sql_content;

	if ($num > 1) $messages[] = '<div class="alert alert-success">Import finished. '.$num.' records successfully imported.</div>';
}
else
{
	$messages[] = '<div class="alert alert-error">Please fill in all fields.</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Contao news importer</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.min.css" rel="stylesheet">
	<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/js/bootstrap.min.js"></script>
	<style type="text/css">
		body { position: relative; padding-top: 40px; }
		h1 { margin-bottom: 40px; }
		textarea { width: 100%; box-sizing: border-box; height: 180px; }
	</style>
</head>
<body>
	<div class="container">
		<div class="row">
			<h1>Contao news importer</h1>
			<?php
			if (count($messages))
			{
				foreach ($messages as $message)
				{
					echo $message;
				}
			}
			?>

			<?php if (count($log)): ?>
			<h2>Log</h2>
			<textarea><?php
				foreach ($log as $message)
				{
					echo ($message.chr(10));
				}
			?></textarea>
			<?php endif ?>

			<h2>Import settings</h2>
			<form method="post">
				<div class="row">
				  	<fieldset class="span6">
					    <legend>MySQL Source</legend>
					    <label>Host</label>
					    <input name="source_host" type="text" placeholder="localhost" value="<?php echo $_POST['source_host'] ?>">
					  	<label>User</label>
					    <input name="source_user" type="text" value="<?php echo $_POST['source_user'] ?>">
					    <label>Password</label>
					    <input name="source_password" type="text" value="<?php echo $_POST['source_password'] ?>">
					    <label>Database</label>
					    <input name="source_database" type="text" value="<?php echo $_POST['source_database'] ?>">
					    <label>Archive pid</label>
					    <input name="source_pid" type="text" value="<?php echo $_POST['source_pid'] ?>">
					    <label>Image path (search)</label>
					    <input name="source_image_path" type="text" placeholder="tl_files/news" value="<?php echo $_POST['source_image_path'] ?>">
				  	</fieldset>
				  	<fieldset class="span6">
					    <legend>MySQL Target</legend>
					    <label>Host</label>
					    <input name="target_host" type="text" placeholder="localhost" value="<?php echo $_POST['target_host'] ?>">
					  	<label>User</label>
					    <input name="target_user" type="text" value="<?php echo $_POST['target_user'] ?>">
					    <label>Password</label>
					    <input name="target_password" type="text" value="<?php echo $_POST['target_password'] ?>">
					    <label>Database</label>
					    <input name="target_database" type="text" value="<?php echo $_POST['target_database'] ?>">
					    <label>Archive pid</label>
					    <input name="target_pid" type="text" value="<?php echo $_POST['target_pid'] ?>">
					    <label>Image path (replace)</label>
					    <input name="target_image_path" type="text" placeholder="files/news" value="<?php echo $_POST['target_image_path'] ?>">
				  	</fieldset>
				</div>
				<div class="control-group">
					<div class="controls">
					  	<label class="checkbox">
				      	<input name="testing_mode" type="checkbox" value="1" checked="checked"> Testing mode
				    	</label>
				    	<label class="checkbox">
				      	<input name="import_image" type="checkbox" value="1" checked="checked"> Import main image
				    	</label>
				    	<button type="submit" class="btn btn-large btn-primary">Import</button>
				    </div>
				</div>
			</form>
		</div>
	</div>
</body>
</html>