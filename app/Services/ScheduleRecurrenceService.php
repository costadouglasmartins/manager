<?php

namespace App\Services;

/**
 * Calculates schedule event occurrences from a recurrence payload.
 **/
class ScheduleRecurrenceService
	{
	private const MAX_OCCURRENCES = 1000;

	/**
	 * Builds recurrence occurrences using an inclusive date period.
	 *
	 * @param array<string, mixed> $payload Normalized recurrence payload.
	 * @return array<int, array{sequence: int, starts_at: string, ends_at: string|null}>
	 **/
	public function buildOccurrences(array $payload): array
		{
		$frequency = (string) ($payload['frequency'] ?? '');
		$interval = max(1, (int) ($payload['interval_value'] ?? 1));
		$periodStart = $this->date((string) ($payload['period_start'] ?? ''));
		$periodEnd = $this->date((string) ($payload['period_end'] ?? ''));
		$allowWeekends = (int) ($payload['allow_weekends'] ?? 1) === 1;
		$allDay = (int) ($payload['all_day'] ?? 0) === 1;
		$startTime = $allDay ? '00:00:00' : (string) ($payload['start_time'] ?? '');
		$endTime = $allDay ? null : ($payload['end_time'] ?? null);

		if ($periodEnd < $periodStart)
			{
			throw new \InvalidArgumentException('A data final da recorrência deve ser maior ou igual à data inicial.');
			}

		if (!$allDay && $startTime === '')
			{
			throw new \InvalidArgumentException('Informe o horário inicial da recorrência.');
			}

		$occurrences = [];
		$current = $periodStart;
		$anchorDay = (int) $periodStart->format('d');
		$sequence = 1;

		while ($current <= $periodEnd)
			{
			if ($allowWeekends || !$this->isWeekend($current))
				{
				$startsAt = $current->format('Y-m-d') . ' ' . $startTime;
				$endsAt = null;

				if (!$allDay && is_string($endTime) && $endTime !== '')
					{
					$endDateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $current->format('Y-m-d') . ' ' . $endTime);
					$startDateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startsAt);
					if ($endDateTime === false || $startDateTime === false || $endDateTime <= $startDateTime)
						{
						throw new \InvalidArgumentException('O horário final da recorrência deve ser maior que o horário inicial.');
						}

					$endsAt = $endDateTime->format('Y-m-d H:i:s');
					}

				$occurrences[] = [
					'sequence' => $sequence,
					'starts_at' => $startsAt,
					'ends_at' => $endsAt,
				];
				$sequence++;

				if (count($occurrences) > self::MAX_OCCURRENCES)
					{
					throw new \InvalidArgumentException('A recorrência gera eventos demais. Reduza o período ou aumente o intervalo.');
					}
				}

			$current = $this->nextDate($current, $frequency, $interval, $anchorDay);
			}

		if ($occurrences === [])
			{
			throw new \InvalidArgumentException('Nenhuma data válida foi gerada para a recorrência.');
			}

		return $occurrences;
		}

	/**
	 * Returns the next recurrence date.
	 **/
	private function nextDate(\DateTimeImmutable $date, string $frequency, int $interval, int $anchorDay): \DateTimeImmutable
		{
		if ($frequency === 'daily')
			{
			return $date->modify('+' . $interval . ' day');
			}

		if ($frequency === 'weekly')
			{
			return $date->modify('+' . $interval . ' week');
			}

		if ($frequency === 'monthly')
			{
			return $this->addMonths($date, $interval, $anchorDay);
			}

		throw new \InvalidArgumentException('Frequência de recorrência inválida.');
		}

	/**
	 * Adds months while preserving the original day when possible.
	 **/
	private function addMonths(\DateTimeImmutable $date, int $months, int $anchorDay): \DateTimeImmutable
		{
		$target = $date->modify('first day of +' . $months . ' month');
		$lastDay = (int) $target->format('t');
		$day = min($anchorDay, $lastDay);

		return $target->setDate((int) $target->format('Y'), (int) $target->format('m'), $day);
		}

	/**
	 * Parses a strict date.
	 **/
	private function date(string $value): \DateTimeImmutable
		{
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
		$errors = \DateTimeImmutable::getLastErrors();
		if ($date === false || (($errors['warning_count'] ?? 0) > 0) || (($errors['error_count'] ?? 0) > 0))
			{
			throw new \InvalidArgumentException('Informe datas válidas para a recorrência.');
			}

		return $date;
		}

	/**
	 * Returns true for Saturday or Sunday.
	 **/
	private function isWeekend(\DateTimeImmutable $date): bool
		{
		return in_array((int) $date->format('N'), [6, 7], true);
		}
	}
?>
