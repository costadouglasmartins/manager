<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\TenantAwareController;
use App\Core\TenantContext;
use App\Models\ScheduleCategory;
use App\Models\ScheduleEvent;

class ScheduleController extends TenantAwareController
	{
	protected $routeAliases = [
		'export-excel' => 'exportExcel',
		'export-pdf' => 'exportPdf',
		'calendar-events' => 'calendarEvents',
		'participant-autocomplete' => 'participantAutocomplete',
	];

	/**
	 * Returns the authenticated manager user id.
	 **/
	private function currentUserId(): int
		{
		return (int) ($_SESSION['manager']['user']['id'] ?? 0);
		}

	/**
	 * Returns the active tenant id from session context.
	 **/
	private function currentTenantId(): int
		{
		$tenant = TenantContext::tenant();
		return (int) ($tenant['id'] ?? 0);
		}

	/**
	 * Reads schedule list filters from the current request.
	 **/
	private function readFilters(): array
		{
		return [
			'id'                    => isset($_GET['id']) ? trim((string) $_GET['id']) : '',
			'schedule_category_id'  => isset($_GET['schedule_category_id']) ? trim((string) $_GET['schedule_category_id']) : '',
			'status'                => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
			'participant_person_id' => isset($_GET['participant_person_id']) ? trim((string) $_GET['participant_person_id']) : '',
			'title'                 => isset($_GET['title']) ? trim((string) $_GET['title']) : '',
			'location'              => isset($_GET['location']) ? trim((string) $_GET['location']) : '',
			'starts_from'           => isset($_GET['starts_from']) ? trim((string) $_GET['starts_from']) : '',
			'starts_to'             => isset($_GET['starts_to']) ? trim((string) $_GET['starts_to']) : '',
		];
		}

	/**
	 * Reads and normalizes schedule ordering inputs.
	 **/
	private function readOrder(): array
		{
		$sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'starts_at';
		$direction = isset($_GET['direction']) ? trim((string) $_GET['direction']) : 'asc';

		return [
			'sort' => $sort,
			'direction' => strtolower($direction) === 'desc' ? 'desc' : 'asc',
		];
		}

	/**
	 * Lists schedule events with filters, ordering and pagination.
	 **/
	public function index(): void
		{
		$this->requirePermission('schedule.index');

		$schedule = new ScheduleEvent();
		$category = new ScheduleCategory();
		$filters = $this->readFilters();
		$order = $this->readOrder();

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$events = $schedule->search($filters, $perPage, $offset, $order['sort'], $order['direction']);
		$total = $schedule->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'data'       => $events,
			]);
			}

		$this->view('schedule_events/index', [
			'events'      => $events,
			'categories'  => $category->getOptions(false),
			'people'      => $schedule->getPersonOptions($this->currentTenantId()),
			'statuses'    => ScheduleEvent::statuses(),
			'title'       => 'Agenda',
			'filters'     => $filters,
			'sort'        => $order['sort'],
			'direction'   => $order['direction'],
			'page'        => $page,
			'totalPages'  => $totalPages,
			'total'       => $total,
		]);
		}

	/**
	 * Downloads all schedule records matching the current list filters as PDF.
	 **/
	public function exportPdf(): void
		{
		parent::exportPdf();
		}

	/**
	 * Downloads all schedule records matching the current list filters as XLSX.
	 **/
	public function exportExcel(): void
		{
		parent::exportExcel();
		}

	/**
	 * Renders the calendar view.
	 **/
	public function calendar(): void
		{
		$this->requirePermission('schedule.calendar');

		$category = new ScheduleCategory();
		$this->view('schedule_events/calendar', [
			'title' => 'Calendário da agenda',
			'categories' => $category->getOptions(false),
		]);
		}

	/**
	 * Returns JSON events for FullCalendar.
	 **/
	public function calendarEvents(): void
		{
		$this->requireScheduleViewAccess();

		$start = isset($_GET['start']) ? (string) $_GET['start'] : '';
		$end = isset($_GET['end']) ? (string) $_GET['end'] : '';
		$schedule = new ScheduleEvent();
		$rows = $schedule->calendarEvents($start, $end);

		$events = array_map(function (array $row): array
			{
			$allDay = (int) ($row['all_day'] ?? 0) === 1;
			$startsAt = (string) ($row['starts_at'] ?? '');
			$endsAt = (string) ($row['ends_at'] ?? '');
			$background = (string) ($row['schedule_category_background_color'] ?? '#0d6efd');
			$text = (string) ($row['schedule_category_description_color'] ?? '#ffffff');

			$item = [
				'id' => (string) ($row['id'] ?? ''),
				'title' => (string) ($row['title'] ?? ''),
				'start' => $this->calendarDateValue($startsAt, $allDay, false),
				'allDay' => $allDay,
				'backgroundColor' => $background !== '' ? $background : '#0d6efd',
				'borderColor' => $background !== '' ? $background : '#0d6efd',
				'textColor' => $text !== '' ? $text : '#ffffff',
				'url' => url('schedule/informations/' . (int) ($row['id'] ?? 0)),
				'extendedProps' => [
					'category' => (string) ($row['schedule_category_name'] ?? ''),
					'location' => (string) ($row['location'] ?? ''),
					'participants' => (string) ($row['participant_names'] ?? ''),
					'status' => (string) ($row['status'] ?? ''),
					'status_label' => ScheduleEvent::statusLabel((string) ($row['status'] ?? '')),
				],
			];

			if ($endsAt !== '')
				{
				$item['end'] = $this->calendarDateValue($endsAt, $allDay, true);
				}

			return $item;
			}, $rows);

		$this->json($events);
		}

	/**
	 * Renders the schedule event creation form.
	 **/
	public function create(): void
		{
		$this->requirePermission('schedule.create');

		$schedule = new ScheduleEvent();
		$category = new ScheduleCategory();
		$this->view('schedule_events/create', [
			'title'       => 'Novo evento',
			'categories'  => $category->getOptions(),
			'people'      => $schedule->getPersonOptions($this->currentTenantId()),
			'statuses'    => ScheduleEvent::statuses(),
			'frequencies' => ScheduleEvent::frequencies(),
		]);
		}

	/**
	 * Persists a new schedule event.
	 **/
	public function store(): void
		{
		$this->requirePermission('schedule.store');

		$schedule = new ScheduleEvent();
		$category = new ScheduleCategory();

		try
			{
			$schedule->create($_POST, $this->currentUserId(), $this->currentTenantId());
			$this->redirect('schedule');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			http_response_code(422);
			$this->view('schedule_events/create', [
				'title'       => 'Novo evento',
				'categories'  => $category->getOptions(),
				'people'      => $schedule->getPersonOptions($this->currentTenantId()),
				'statuses'    => ScheduleEvent::statuses(),
				'frequencies' => ScheduleEvent::frequencies(),
				'formData'    => $_POST,
				'formError'   => $e->getMessage(),
			]);
			}
		}

	/**
	 * Renders the schedule event edit form.
	 *
	 * @param mixed $id Event identifier.
	 **/
	public function edit($id): void
		{
		$this->requirePermission('schedule.edit');

		$schedule = new ScheduleEvent();
		$data = $schedule->findAllByScheduleEventId($id);
		if (empty($data['schedule_event']))
			{
			$this->redirect('schedule');
			}

		$category = new ScheduleCategory();
		$this->view('schedule_events/edit', [
			'title'       => 'Editar evento',
			'event'       => $data['schedule_event'],
			'participants' => $data['participants'],
			'recurrence'  => $data['recurrence'],
			'categories'  => $category->getOptions(false),
			'people'      => $schedule->getPersonOptions($this->currentTenantId()),
			'statuses'    => ScheduleEvent::statuses(),
		]);
		}

	/**
	 * Updates an existing schedule event.
	 *
	 * @param mixed $id Event identifier.
	 **/
	public function update($id): void
		{
		$this->requirePermission('schedule.update');

		$schedule = new ScheduleEvent();
		$category = new ScheduleCategory();

		try
			{
			$schedule->update($id, $_POST, $this->currentTenantId());
			$this->redirect('schedule');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$data = $schedule->findAllByScheduleEventId($id);
			http_response_code(422);
			$this->view('schedule_events/edit', [
				'title'       => 'Editar evento',
				'event'       => $data['schedule_event'] ?? [],
				'participants' => $data['participants'] ?? [],
				'recurrence'  => $data['recurrence'] ?? null,
				'categories'  => $category->getOptions(false),
				'people'      => $schedule->getPersonOptions($this->currentTenantId()),
				'statuses'    => ScheduleEvent::statuses(),
				'formData'    => $_POST,
				'formError'   => $e->getMessage(),
			]);
			}
		}

	/**
	 * Deletes an existing schedule event.
	 *
	 * @param mixed $id Event identifier.
	 **/
	public function delete($id): void
		{
		$this->requirePermission('schedule.delete');

		$schedule = new ScheduleEvent();
		$category = new ScheduleCategory();

		try
			{
			$schedule->delete($id);
			$this->redirect('schedule');
			}
		catch (\Throwable $e)
			{
			if ($this->wantsJson())
				{
				$this->json(['error' => $e->getMessage()], 422);
				}

			$filters = $this->readFilters();
			$order = $this->readOrder();
			$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
			if ($page < 1)
				{
				$page = 1;
				}

			$perPage = 10;
			$offset = ($page - 1) * $perPage;
			$events = $schedule->search($filters, $perPage, $offset, $order['sort'], $order['direction']);
			$total = $schedule->count($filters);
			$totalPages = (int) ceil($total / $perPage);
			if ($totalPages < 1)
				{
				$totalPages = 1;
				}

			http_response_code(422);
			$this->view('schedule_events/index', [
				'events'      => $events,
				'categories'  => $category->getOptions(false),
				'people'      => $schedule->getPersonOptions($this->currentTenantId()),
				'statuses'    => ScheduleEvent::statuses(),
				'title'       => 'Agenda',
				'filters'     => $filters,
				'sort'        => $order['sort'],
				'direction'   => $order['direction'],
				'page'        => $page,
				'totalPages'  => $totalPages,
				'total'       => $total,
				'formError'   => $e->getMessage(),
			]);
			}
		}

	/**
	 * Displays schedule event details.
	 *
	 * @param mixed $id Event identifier.
	 **/
	public function informations($id): void
		{
		$this->requirePermission('schedule.informations');

		$schedule = new ScheduleEvent();
		$data = $schedule->findAllByScheduleEventId($id);
		if (empty($data['schedule_event']))
			{
			$this->redirect('schedule');
			}

		$this->view('schedule_events/informations', [
			'title'       => 'Informações do evento',
			'event'       => $data['schedule_event'],
			'participants' => $data['participants'],
			'recurrence'  => $data['recurrence'],
			'statuses'    => ScheduleEvent::statuses(),
		]);
		}

	/**
	 * Returns tenant-scoped person suggestions for participant fields.
	 **/
	public function participantAutocomplete(): void
		{
		$this->requireScheduleFormAccess();

		$term = isset($_GET['term']) ? trim((string) $_GET['term']) : '';
		$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
		$schedule = new ScheduleEvent();
		$rows = $schedule->autocompletePeople($term, $this->currentTenantId(), $limit);

		$items = array_map(static function (array $row): array
			{
			$label = trim((string) ($row['name'] ?? ''));
			if ($label === '')
				{
				$label = (string) ($row['id'] ?? '');
				}

			return [
				'id' => isset($row['id']) ? (int) $row['id'] : null,
				'label' => $label,
				'name' => $row['name'] ?? null,
			];
			}, $rows);

		$this->json(['items' => $items]);
		}

	/**
	 * Allows access to JSON endpoints for users that can view the schedule.
	 **/
	private function requireScheduleViewAccess(): void
		{
		if (Authorization::can('schedule.calendar') || Authorization::can('schedule.index'))
			{
			return;
			}

		$this->requirePermission('schedule.index');
		}

	/**
	 * Allows access to helper endpoints for users that can create or edit schedule events.
	 **/
	private function requireScheduleFormAccess(): void
		{
		if (Authorization::can('schedule.create') || Authorization::can('schedule.edit') || Authorization::can('schedule.update'))
			{
			return;
			}

		$this->requirePermission('schedule.create');
		}

	/**
	 * Formats a calendar date for FullCalendar.
	 **/
	private function calendarDateValue(string $value, bool $allDay, bool $isEnd): string
		{
		if ($allDay)
			{
			try
				{
				$date = new \DateTimeImmutable(substr($value, 0, 10));
				if ($isEnd)
					{
					$date = $date->modify('+1 day');
					}

				return $date->format('Y-m-d');
				}
			catch (\Throwable $e)
				{
				return substr($value, 0, 10);
				}
			}

		return str_replace(' ', 'T', $value);
		}
	}
?>
