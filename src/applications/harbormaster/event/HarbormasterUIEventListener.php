<?php

final class HarbormasterUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        $this->handlePropertyEvent($event);
        break;
    }
  }

  private function handlePropertyEvent($ui_event) {
    $viewer = $ui_event->getUser();
    $object = $ui_event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    if ($object instanceof HarbormasterBuildable) {
      // Although HarbormasterBuildable implements the correct interface, it
      // does not make sense to show a build's build status. In the best case
      // it is meaningless, and in the worst case it's confusing.
      return;
    }

    if ($object instanceof DifferentialRevision) {
      // TODO: This is a bit hacky and we could probably find a cleaner fix
      // eventually, but we show build status on each diff, immediately below
      // this property list, so it's redundant to show it on the revision view.
      return;
    }

    if (!($object instanceof HarbormasterBuildableInterface)) {
      return;
    }

    $buildable_phid = $object->getHarbormasterBuildablePHID();
    if (!$buildable_phid) {
      return;
    }

    if (!$this->canUseApplication($ui_event->getUser())) {
      return;
    }

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withManualBuildables(false)
      ->withBuildablePHIDs(array($buildable_phid))
      ->needBuilds(true)
      ->needTargets(true)
      ->executeOne();
    if (!$buildable) {
      return;
    }

    $builds = $buildable->getBuilds();

    $targets = array();
    foreach ($builds as $build) {
      foreach ($build->getBuildTargets() as $target) {
        $targets[] = $target;
      }
    }

    if ($targets) {
      $artifacts = id(new HarbormasterBuildArtifactQuery())
        ->setViewer($viewer)
        ->withBuildTargetPHIDs(mpull($targets, 'getPHID'))
        ->withArtifactTypes(
          array(
            HarbormasterURIArtifact::ARTIFACTCONST,
          ))
        ->execute();
      $artifacts = mgroup($artifacts, 'getBuildTargetPHID');
    } else {
      $artifacts = array();
    }

    $status_view = new PHUIStatusListView();

    $buildable_status = $buildable->getBuildableStatus();
    $buildable_icon = HarbormasterBuildable::getBuildableStatusIcon(
      $buildable_status);
    $buildable_color = HarbormasterBuildable::getBuildableStatusColor(
      $buildable_status);
    $buildable_name = HarbormasterBuildable::getBuildableStatusName(
      $buildable_status);

    $target = phutil_tag(
      'a',
      array(
        'href' => '/'.$buildable->getMonogram(),
      ),
      pht('Buildable %d', $buildable->getID()));

    $target = phutil_tag('strong', array(), $target);


    $status_view
      ->addItem(
        id(new PHUIStatusItemView())
          ->setIcon($buildable_icon, $buildable_color, $buildable_name)
          ->setTarget($target));

    foreach ($builds as $build) {
      $item = new PHUIStatusItemView();
      $item->setTarget($viewer->renderHandle($build->getPHID()));

      $links = array();
      foreach ($build->getBuildTargets() as $build_target) {
        $uris = idx($artifacts, $build_target->getPHID(), array());
        foreach ($uris as $uri) {
          $impl = $uri->getArtifactImplementation();
          if ($impl->isExternalLink()) {
            $links[] = $impl->renderLink();
          }
        }
      }

      if ($links) {
        $links = phutil_implode_html(" \xC2\xB7 ", $links);
        $item->setNote($links);
      }

      $status = $build->getBuildStatus();
      $status_name = HarbormasterBuild::getBuildStatusName($status);
      $icon = HarbormasterBuild::getBuildStatusIcon($status);
      $color = HarbormasterBuild::getBuildStatusColor($status);

      $item->setIcon($icon, $color, $status_name);

      $status_view->addItem($item);
    }

    $view = $ui_event->getValue('view');
    $view->addProperty(pht('Build Status'), $status_view);
  }

}
