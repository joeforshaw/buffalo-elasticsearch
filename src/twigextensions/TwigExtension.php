<?php

namespace buffalo\elasticsearch\twigextensions;

use Twig_Extension as BaseTwigExtension;
use \Twig\TwigFunction;
use \buffalo\elasticsearch\Elasticsearch;

class TwigExtension extends BaseTwigExtension
{
	public function getFunctions()
	{
		return [
			new TwigFunction('advanced_search', [$this, 'advancedSearch']),
			new TwigFunction('autocomplete', [$this, 'autocomplete']),
		];
	}

	public function advancedSearch($search)
	{
		return Elasticsearch::getInstance()->service->search($search);
	}

	public function autocomplete($search)
	{
		return Elasticsearch::getInstance()->service->autocomplete($search);
	}
}
