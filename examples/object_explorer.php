<?php
	require __DIR__ . '/config.php';

	use TibiaReader\ThingCategory;

	$object = new TibiaReader\DatObject();

	$category = ThingCategory::from(queryInt('type', ThingCategory::Item->value, 0, count(ThingCategory::cases()) - 1));

	[$minId, $maxId] = match ($category) {
		ThingCategory::Item       => [100, $object->fileItems],
		ThingCategory::Looktype   => [1, $object->fileLooktypes],
		ThingCategory::Effect     => [1, $object->fileEffects],
		ThingCategory::Projectile => [1, $object->fileProjectiles],
	};

	// An id outside the file shows the form again with a note instead of an error page
	$formId = queryInt('id', $minId, 0, 0xFFFF);
	$thing = ($formId >= $minId && $formId <= $maxId) ? $object->get($category, $formId) : null;
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Object explorer</title>
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
				<?php foreach (ThingCategory::cases() as $option) { ?>
					<option value="<?php print $option->value; ?>"<?php print $option === $category ? ' selected' : ''; ?>><?php print $option->label(); ?></option>
				<?php } ?>
			</select>
			<input type="submit" value="show">
		</form>
	</div>
	<?php if ($thing === null) { ?>
		<div id="objectProperties">
			No <?php print $category->label(); ?> <?php print $formId; ?>. This file has <?php print $category->label(); ?> ids <?php print $minId; ?> to <?php print $maxId; ?>.
		</div>
	<?php } else { ?>
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
					print '<img src="sprite.php?id=' . $id . '" width="32" height="32" loading="lazy" alt="">';
					if (($index + 1) % 16 == 0) {
						print '<div style="clear: left"></div>';
					}
				}
				print '</div>';
				print '</div>';
			}
		?>
	</div>
	<?php } ?>
</body>
</html>
