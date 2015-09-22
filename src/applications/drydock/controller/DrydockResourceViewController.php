<?php

final class DrydockResourceViewController extends DrydockResourceController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$resource) {
      return new Aphront404Response();
    }

    $title = pht('Resource %s %s', $resource->getID(), $resource->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($resource);
    $properties = $this->buildPropertyListView($resource, $actions);

    $resource_uri = 'resource/'.$resource->getID().'/';
    $resource_uri = $this->getApplicationURI($resource_uri);

    $leases = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withResourceIDs(array($resource->getID()))
      ->execute();

    $lease_list = id(new DrydockLeaseListView())
      ->setUser($viewer)
      ->setLeases($leases)
      ->render();
    $lease_list->setNoDataString(pht('This resource has no leases.'));

    $pager = new PHUIPagerView();
    $pager->setURI(new PhutilURI($resource_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $logs = id(new DrydockLogQuery())
      ->setViewer($viewer)
      ->withResourceIDs(array($resource->getID()))
      ->executeWithOffsetPager($pager);

    $log_table = id(new DrydockLogListView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->render();
    $log_table->appendChild($pager);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Resource %d', $resource->getID()));

    $locks = $this->buildLocksTab($resource->getPHID());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties, pht('Properties'))
      ->addPropertyList($locks, pht('Slot Locks'));

    $lease_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Leases'))
      ->setObjectList($lease_list);

    $log_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Resource Logs'))
      ->setTable($log_table);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $lease_box,
        $log_box,
      ),
      array(
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockResource $resource) {
    $view = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($resource);

    $can_close = ($resource->getStatus() == DrydockResourceStatus::STATUS_OPEN);
    $uri = '/resource/'.$resource->getID().'/close/';
    $uri = $this->getApplicationURI($uri);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName(pht('Close Resource'))
        ->setIcon('fa-times')
        ->setWorkflow(true)
        ->setDisabled(!$can_close));

    return $view;
  }

  private function buildPropertyListView(
    DrydockResource $resource,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    $status = $resource->getStatus();
    $status = DrydockResourceStatus::getNameForStatus($status);

    $view->addProperty(
      pht('Status'),
      $status);

    $view->addProperty(
      pht('Resource Type'),
      $resource->getType());

    $view->addProperty(
      pht('Blueprint'),
      $viewer->renderHandle($resource->getBlueprintPHID()));

    $attributes = $resource->getAttributes();
    if ($attributes) {
      $view->addSectionHeader(
        pht('Attributes'), 'fa-list-ul');
      foreach ($attributes as $key => $value) {
        $view->addProperty($key, $value);
      }
    }

    return $view;
  }

}
