<?php declare(strict_types = 1);

namespace Pd\Monitoring\DashBoard\Controls\Maintenance;

class Control extends \Nette\Application\UI\Control
{

	/**
	 * @var \Pd\Monitoring\Project\Project
	 */
	private $project;

	/**
	 * @var \Pd\Monitoring\Project\ProjectsRepository
	 */
	private $projectsRepository;

	/**
	 * @var \Kdyby\Clock\IDateTimeProvider
	 */
	private $dateTimeProvider;

	/**
	 * @var array|IOnToggle[]
	 */
	private $onToggleHandlers = [];

	/**
	 * @var \Pd\Monitoring\Slack\Notifier
	 */
	private $notifier;

	/**
	 * @var \Nette\Security\User
	 */
	private $user;


	public function __construct(
		\Pd\Monitoring\Project\Project $project,
		\Pd\Monitoring\Project\ProjectsRepository $projectsRepository,
		\Kdyby\Clock\IDateTimeProvider $dateTimeProvider,
		\Pd\Monitoring\Slack\Notifier $notifier,
		\Nette\Security\User $user
	) {
		parent::__construct();
		$this->project = $project;
		$this->projectsRepository = $projectsRepository;
		$this->dateTimeProvider = $dateTimeProvider;
		$this->notifier = $notifier;
		$this->user = $user;
	}


	protected function createTemplate()
	{
		/** @var \Latte\Runtime\Template $template */
		$template = parent::createTemplate();

		$template->addFilter('dateTime', function (\DateTime $value) {
			return $value->format('j. n. Y H:i:s');
		});

		return $template;
	}


	public function render()
	{
		$this->template->project = $this->project;

		$this->template->setFile(__DIR__ . '/Control.latte');
		$this->template->render();
	}


	public function handleToggle()
	{
		if ($this->project->maintenance) {
			$this->project->maintenance = NULL;
			$action = 'vypnul';
		} else {
			$this->project->maintenance = $this->dateTimeProvider->getDateTime();
			$action = 'zapnul';
		}

		$this->projectsRepository->persistAndFlush($this->project);

		/** @var \Pd\Monitoring\User\User $identity */
		$identity = $this->user->getIdentity();
		$statusMessage = sprintf(
			'%s %s údržbu projektu %s.',
			$identity->gitHubName,
			$action,
			$this->project->name
		);
		$this->notifier->notify($statusMessage, 'good');

		foreach ($this->onToggleHandlers as $handler) {
			$handler->process($this);
		}

		$this->processRequest();
	}


	private function processRequest()
	{
		if ($this->getPresenter()->isAjax()) {
			$this->redrawControl();
		} else {
			$this->redirect('this');
		}
	}


	public function addOnToggle(IOnToggle $handler)
	{
		$this->onToggleHandlers[] = $handler;
	}

}
