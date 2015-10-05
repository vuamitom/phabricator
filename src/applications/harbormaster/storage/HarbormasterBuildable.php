<?php

final class HarbormasterBuildable extends HarbormasterDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    HarbormasterBuildableInterface {

  protected $buildablePHID;
  protected $containerPHID;
  protected $buildableStatus;
  protected $isManualBuildable;

  private $buildableObject = self::ATTACHABLE;
  private $containerObject = self::ATTACHABLE;
  private $buildableHandle = self::ATTACHABLE;
  private $containerHandle = self::ATTACHABLE;
  private $builds = self::ATTACHABLE;

  const STATUS_BUILDING = 'building';
  const STATUS_PASSED = 'passed';
  const STATUS_FAILED = 'failed';

  public static function getBuildableStatusName($status) {
    switch ($status) {
      case self::STATUS_BUILDING:
        return pht('Building');
      case self::STATUS_PASSED:
        return pht('Passed');
      case self::STATUS_FAILED:
        return pht('Failed');
      default:
        return pht('Unknown');
    }
  }

  public static function getBuildableStatusIcon($status) {
    switch ($status) {
      case self::STATUS_BUILDING:
        return PHUIStatusItemView::ICON_RIGHT;
      case self::STATUS_PASSED:
        return PHUIStatusItemView::ICON_ACCEPT;
      case self::STATUS_FAILED:
        return PHUIStatusItemView::ICON_REJECT;
      default:
        return PHUIStatusItemView::ICON_QUESTION;
    }
  }

  public static function getBuildableStatusColor($status) {
    switch ($status) {
      case self::STATUS_BUILDING:
        return 'blue';
      case self::STATUS_PASSED:
        return 'green';
      case self::STATUS_FAILED:
        return 'red';
      default:
        return 'bluegrey';
    }
  }

  public static function initializeNewBuildable(PhabricatorUser $actor) {
    return id(new HarbormasterBuildable())
      ->setIsManualBuildable(0)
      ->setBuildableStatus(self::STATUS_BUILDING);
  }

  public function getMonogram() {
    return 'B'.$this->getID();
  }

  /**
   * Returns an existing buildable for the object's PHID or creates a
   * new buildable implicitly if needed.
   */
  public static function createOrLoadExisting(
    PhabricatorUser $actor,
    $buildable_object_phid,
    $container_object_phid) {

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($actor)
      ->withBuildablePHIDs(array($buildable_object_phid))
      ->withManualBuildables(false)
      ->setLimit(1)
      ->executeOne();
    if ($buildable) {
      return $buildable;
    }
    $buildable = self::initializeNewBuildable($actor)
      ->setBuildablePHID($buildable_object_phid)
      ->setContainerPHID($container_object_phid);
    $buildable->save();
    return $buildable;
  }

  /**
   * Start builds for a given buildable.
   *
   * @param phid PHID of the object to build.
   * @param phid Container PHID for the buildable.
   * @param list<HarbormasterBuildRequest> List of builds to perform.
   * @return void
   */
  public static function applyBuildPlans(
    $phid,
    $container_phid,
    array $requests) {

    assert_instances_of($requests, 'HarbormasterBuildRequest');

    if (!$requests) {
      return;
    }

    // Skip all of this logic if the Harbormaster application
    // isn't currently installed.

    $harbormaster_app = 'PhabricatorHarbormasterApplication';
    if (!PhabricatorApplication::isClassInstalled($harbormaster_app)) {
      return;
    }

    $viewer = PhabricatorUser::getOmnipotentUser();

    $buildable = self::createOrLoadExisting(
      $viewer,
      $phid,
      $container_phid);

    $plan_phids = mpull($requests, 'getBuildPlanPHID');
    $plans = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withPHIDs($plan_phids)
      ->execute();
    $plans = mpull($plans, null, 'getPHID');

    foreach ($requests as $request) {
      $plan_phid = $request->getBuildPlanPHID();
      $plan = idx($plans, $plan_phid);

      if (!$plan) {
        throw new Exception(
          pht(
            'Failed to load build plan ("%s").',
            $plan_phid));
      }

      if ($plan->isDisabled()) {
        // TODO: This should be communicated more clearly -- maybe we should
        // create the build but set the status to "disabled" or "derelict".
        continue;
      }

      $parameters = $request->getBuildParameters();
      $buildable->applyPlan($plan, $parameters);
    }
  }

  public function applyPlan(HarbormasterBuildPlan $plan, array $parameters) {

    $viewer = PhabricatorUser::getOmnipotentUser();
    $build = HarbormasterBuild::initializeNewBuild($viewer)
      ->setBuildablePHID($this->getPHID())
      ->setBuildPlanPHID($plan->getPHID())
      ->setBuildParameters($parameters)
      ->setBuildStatus(HarbormasterBuild::STATUS_PENDING);

    $auto_key = $plan->getPlanAutoKey();
    if ($auto_key) {
      $build->setPlanAutoKey($auto_key);
    }

    $build->save();

    PhabricatorWorker::scheduleTask(
      'HarbormasterBuildWorker',
      array(
        'buildID' => $build->getID(),
      ));

    return $build;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'containerPHID' => 'phid?',
        'buildableStatus' => 'text32',
        'isManualBuildable' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_buildable' => array(
          'columns' => array('buildablePHID'),
        ),
        'key_container' => array(
          'columns' => array('containerPHID'),
        ),
        'key_manual' => array(
          'columns' => array('isManualBuildable'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterBuildablePHIDType::TYPECONST);
  }

  public function attachBuildableObject($buildable_object) {
    $this->buildableObject = $buildable_object;
    return $this;
  }

  public function getBuildableObject() {
    return $this->assertAttached($this->buildableObject);
  }

  public function attachContainerObject($container_object) {
    $this->containerObject = $container_object;
    return $this;
  }

  public function getContainerObject() {
    return $this->assertAttached($this->containerObject);
  }

  public function attachContainerHandle($container_handle) {
    $this->containerHandle = $container_handle;
    return $this;
  }

  public function getContainerHandle() {
    return $this->assertAttached($this->containerHandle);
  }

  public function attachBuildableHandle($buildable_handle) {
    $this->buildableHandle = $buildable_handle;
    return $this;
  }

  public function getBuildableHandle() {
    return $this->assertAttached($this->buildableHandle);
  }

  public function attachBuilds(array $builds) {
    assert_instances_of($builds, 'HarbormasterBuild');
    $this->builds = $builds;
    return $this;
  }

  public function getBuilds() {
    return $this->assertAttached($this->builds);
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new HarbormasterBuildableTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new HarbormasterBuildableTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildableObject()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildableObject()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('A buildable inherits policies from the underlying object.');
  }



/* -(  HarbormasterBuildableInterface  )------------------------------------- */


  public function getHarbormasterBuildablePHID() {
    // NOTE: This is essentially just for convenience, as it allows you create
    // a copy of a buildable by specifying `B123` without bothering to go
    // look up the underlying object.
    return $this->getBuildablePHID();
  }

  public function getHarbormasterContainerPHID() {
    return $this->getContainerPHID();
  }

  public function getBuildVariables() {
    return array();
  }

  public function getAvailableBuildVariables() {
    return array();
  }


}
