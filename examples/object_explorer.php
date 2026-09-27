<?php
	require '../TibiaReader.php';

	TibiaReader\Config::setDataFilePath('resources/1084Tibia.dat');
	TibiaReader\Config::setDataCaching(true);


	$object = new TibiaReader\DatObject();

	if (isset($_GET['id'])) {
		$id = (int) $_GET['id'];
		switch ($_GET['type']) {
			case '1': $object->getLooktype($id); break;
			case '2': $object->getEffect($id); break;
			case '3': $object->getProjectile($id); break;
			default:  $object->getItem($id); break;
		}
	} else {
		$object->getItem(100);
	}

	$formId = $_GET['id'] ?? 100;

	$selectedType = ['', '', '', ''];
	if (isset($_GET['type'])) {
		$selectedType[$_GET['type']] = 'selected="selected"';
	}
?>
<!doctype html>
<html>
<head>
	<style>
		body {
			padding: 0;
			margin: 0;
		}
		#information {
			padding: 10px 0 0 100px;
		}
		#information pre{
			margin: 0;
		}
		#navigation {
			border-bottom: 1px dashed gray;
			padding: 10px 0 10px 100px;
		}
		#objectProperties {
			width: 48%;
			min-height: 100%;
			border-right: 1px dashed gray;
			float: left;
			padding: 10px;
		}
		#objectSprites {
			float: left;
			width: 45%;
			padding: 10px;
		}
		.frameGroup {
			margin-bottom: 10px;
		}
		.spritesContainer {
			overflow: hidden;
		}
		.spritesContainer img{
			float: left;
			margin: 0 1px 1px 0;
		}

	</style>
</head>
<body>
	<div id="information">
		<pre>Items: <?php print $object->fileItems; ?>, Looktypes: <?php print $object->fileLooktypes; ?>, Effects: <?php print $object->fileEffects; ?>, Projectiles: <?php print $object->fileProjectiles; ?></pre>
	</div>
	<div id="navigation">
		<form action="?" method="GET">
			<input type="number" value="<?php print $formId; ?>" name="id">
			<select name="type">
				<option value="0" <?php print $selectedType[0]; ?>>item</option>
				<option value="1" <?php print $selectedType[1]; ?>>looktype</option>
				<option value="2" <?php print $selectedType[2]; ?>>effect</option>
				<option value="3" <?php print $selectedType[3]; ?>>projectile</option>
			</select>
			<input type="submit" value="show">
		</form>
	</div>
	<div id="objectProperties">
		<?php $object->printObjectInformation(); ?>
	</div>
	<div id="objectSprites">
		<?php
			$frameGroups = $object->object['frameGroups'] ?? [null => $object->object];

			foreach ($frameGroups as $frameGroup => $group) {
				print '<div class="frameGroup">';
				if ($frameGroup !== null) {
					print 'Framegroup: ' . $frameGroup . ' <br>';
				}
				print '<div class="spritesContainer">';
				foreach ($group['spriteIds'] as $index => $id) {
					print '<img src="sprite.php?id=' . $id . '">';
					if (($index + 1) % 16 == 0) {
						print '<div style="clear: left"></div>';
					}
				}
				print '</div>';
				print '</div>';
			}
		?>
	</div>
</body>
</html>
