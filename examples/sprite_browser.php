<?php
	require __DIR__ . '/config.php';

	$sprites = new TibiaReader\Sprite();

	$spritesPerPage = 300;
	$lastPage = max(1, (int) ceil($sprites->spritesCount / $spritesPerPage));

	$page = queryInt('page', 1, 1, $lastPage);

	// First and last sprite id on a page
	$pageRange = static fn (int $page): array => [
		($page - 1) * $spritesPerPage + 1,
		min($page * $spritesPerPage, $sprites->spritesCount),
	];

	[$begin, $end] = $pageRange($page);
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>Sprite browser</title>
	<style>
		#navigation {
			text-align: center;
			margin-bottom: 10px;
			width: 100%;
			background: lightblue;
			padding: 4px 0;
			font: normal 14px "Arial";
			color: rgb(80, 80, 80);
		}
		#navigation span{
			margin: 0 10px;
		}
		#navigation a{
			text-decoration: none;
			color: rgb(80, 80, 80);
		}
		#navigation a:hover {
			color: rgb(0, 0, 0);
		}

		#sprites {
			font-size: 0;
			margin: 0 auto;
			text-align: center;
		}

		.spritea {
			display: inline-block;
			margin: -1px 0 0 -1px;
			border: 1px solid #fff;
			width: 32px;
		}
		.spritea img{
			margin: 0;
		}
	</style>
</head>
<body>
	<div id="navigation">
		<?php if ($page > 1) { ?>
			<a href="?page=<?php echo $page - 1; ?>">Prev (<?php echo implode('-', $pageRange($page - 1)); ?>)</a>
			<span>|</span>
		<?php } ?>
		Sprites <?php echo $begin; ?>-<?php echo $end; ?> of <?php echo $sprites->spritesCount; ?>
		<?php if ($page < $lastPage) { ?>
			<span>|</span>
			<a href="?page=<?php echo $page + 1; ?>">Next (<?php echo implode('-', $pageRange($page + 1)); ?>)</a>
		<?php } ?>
	</div>

	<div id="sprites">
		<?php
			for ($sprite = $begin; $sprite <= $end; $sprite++) {
				echo '<div class="spritea"><img src="sprite.php?id=' . $sprite . '" width="32" height="32" loading="lazy" alt="' . $sprite . '" title="' . $sprite . '"></div>';
				if (($sprite % 20) == 0) {
					echo "<br>";
				}
			}
		?>
	</div>
</body>
</html>
