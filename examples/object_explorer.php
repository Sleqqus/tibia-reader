<?php
	require __DIR__ . '/config.php';

	$formId = (int) ($_GET['id'] ?? 100);
	$type = (int) ($_GET['type'] ?? 0);

	$object = new TibiaReader\DatObject();

	$thing = $object->get(TibiaReader\ThingCategory::tryFrom($type) ?? TibiaReader\ThingCategory::Item, $formId);

	$selectedType = ['', '', '', ''];
	if (isset($selectedType[$type])) {
		$selectedType[$type] = 'selected="selected"';
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
		<pre>Client <?php print $object->version(); ?>. Items: <?php print $object->fileItems; ?>, Looktypes: <?php print $object->fileLooktypes; ?>, Effects: <?php print $object->fileEffects; ?>, Projectiles: <?php print $object->fileProjectiles; ?></pre>
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
			foreach ($thing->frameGroups as $frameGroupType => $group) {
				print '<div class="frameGroup">';
				print 'Frame group: ' . $frameGroupType . ' <br>';
				print '<div class="spritesContainer">';
				foreach ($group->spriteIds as $index => $id) {
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
