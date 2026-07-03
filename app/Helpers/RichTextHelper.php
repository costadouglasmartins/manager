<?php
/**
 * Rich text sanitization helpers shared by screens using the generic rich text editor.
 */

if (!function_exists('sanitizeRichText'))
	{
	/**
	 * Strips disallowed tags and attributes from rich text editor content.
	 *
	 * @param string|null $html Raw HTML submitted by the rich text editor.
	 * @return string|null Sanitized HTML, or null when the content is empty.
	 */
	function sanitizeRichText(?string $html): ?string
		{
		$html = trim((string) $html);
		if ($html === '')
			{
			return null;
			}

		$allowedTags = '<b><strong><i><em><u><br><p><div><ul><ol><li>';
		$html = strip_tags($html, $allowedTags);
		$html = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $html) ?? '';
		$html = preg_replace('/javascript\s*:/i', '', $html) ?? '';
		$html = trim($html);

		return ($html !== '' ? $html : null);
		}
	}
