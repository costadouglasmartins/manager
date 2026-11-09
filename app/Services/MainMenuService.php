<?php

namespace App\Services;

use App\Core\Authorization;

class MainMenuService
	{
	/**
	 * Callback that tells whether the current user holds an ability.
	 * @var callable
	 **/
	private $can;

	/**
	 * Creates the service with an optional ability checker.
	 * @param callable|null $can Receives an ability and returns true when granted; defaults to the session authorization.
	 **/
	public function __construct(?callable $can = null)
		{
		$this->can = $can ?? static fn (string $ability): bool => Authorization::can($ability);
		}

	/**
	 * Returns the menu entries the current user may see.
	 * Links stay only when one of their abilities is granted, groups only while a visible link remains inside them,
	 * and dividers only between visible entries.
	 * @param array $items Menu definition made of links, groups (`children`) and dividers (`divider`).
	 * @return array Visible entries, with each link carrying its resolved `url`.
	 **/
	public function visibleItems(array $items): array
		{
		$visible = [];

		foreach ($items as $item)
			{
			if (!empty($item['divider']))
				{
				$visible[] = ['divider' => true];
				continue;
				}

			if (isset($item['children']))
				{
				$item['children'] = $this->visibleItems((array) $item['children']);
				if ($item['children'] !== [])
					{
					$visible[] = $item;
					}
				continue;
				}

			$url = $this->grantedUrl($item);
			if ($url !== null)
				{
				$item['url'] = $url;
				unset($item['ability'], $item['targets']);
				$visible[] = $item;
				}
			}

		return $this->trimDividers($visible);
		}

	/**
	 * Returns the URL of the first link target whose ability is granted.
	 * A link declares either `ability` + `url` or an ordered `targets` list of `ability` + `url` pairs;
	 * links without an ability are never shown.
	 * @param array $item Link definition.
	 * @return string|null Granted URL, or null when the link must stay hidden.
	 **/
	private function grantedUrl(array $item): ?string
		{
		$targets = $item['targets'] ?? [['ability' => $item['ability'] ?? '', 'url' => $item['url'] ?? '']];

		foreach ((array) $targets as $target)
			{
			$ability = trim((string) ($target['ability'] ?? ''));
			if ($ability !== '' && ($this->can)($ability))
				{
				return (string) ($target['url'] ?? '');
				}
			}

		return null;
		}

	/**
	 * Removes leading, trailing and repeated dividers.
	 * @param array $items Visible menu entries.
	 * @return array Entries with dividers only between other entries.
	 **/
	private function trimDividers(array $items): array
		{
		$trimmed = [];

		foreach ($items as $item)
			{
			$lastIsDivider = $trimmed === [] || !empty($trimmed[array_key_last($trimmed)]['divider']);
			if (!empty($item['divider']) && $lastIsDivider)
				{
				continue;
				}

			$trimmed[] = $item;
			}

		if ($trimmed !== [] && !empty($trimmed[array_key_last($trimmed)]['divider']))
			{
			array_pop($trimmed);
			}

		return $trimmed;
		}
	}

?>
