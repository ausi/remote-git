<?php

declare(strict_types=1);

/*
 * This file is part of the ausi/remote-git package.
 *
 * (c) Martin Auswöger <martin@auswoeger.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Contao\EasyCodingStandard\Fixer\CommentLengthFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\ControlStructure\YodaStyleFixer;
use PhpCsFixer\Fixer\Operator\NewWithBracesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ECSConfig $ecsConfig): void {
	$ecsConfig->sets([__DIR__.'/vendor/contao/easy-coding-standard/config/contao.php']);

	$ecsConfig->ruleWithConfiguration(NewWithBracesFixer::class, [
		'named_class' => false,
		'anonymous_class' => false,
	]);

	$ecsConfig->ruleWithConfiguration(HeaderCommentFixer::class, [
		'header' => "This file is part of the ausi/remote-git package.\n\n(c) Martin Auswöger <martin@auswoeger.com>\n\nFor the full copyright and license information, please view the LICENSE\nfile that was distributed with this source code.",
	]);

	$ecsConfig->ruleWithConfiguration(YodaStyleFixer::class, [
		'equal' => false,
		'identical' => false,
		'less_and_greater' => false,
	]);

	$ecsConfig->skip([
		CommentLengthFixer::class,
	]);

	$ecsConfig->parallel();
	$ecsConfig->lineEnding("\n");
	$ecsConfig->indentation(Option::INDENTATION_TAB);
	$ecsConfig->cacheDirectory(sys_get_temp_dir().'/ecs_default_cache');
};
