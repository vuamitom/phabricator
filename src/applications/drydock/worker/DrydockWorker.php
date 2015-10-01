<?php

abstract class DrydockWorker extends PhabricatorWorker {

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  protected function loadLease($lease_phid) {
    $viewer = $this->getViewer();

    $lease = id(new DrydockLeaseQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($lease_phid))
      ->executeOne();
    if (!$lease) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such lease "%s"!', $lease_phid));
    }

    return $lease;
  }

  protected function loadResource($resource_phid) {
    $viewer = $this->getViewer();

    $resource = id(new DrydockResourceQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($resource_phid))
      ->executeOne();
    if (!$resource) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('No such resource "%s"!', $resource_phid));
    }

    return $resource;
  }

  protected function loadCommands($target_phid) {
    $viewer = $this->getViewer();

    $commands = id(new DrydockCommandQuery())
      ->setViewer($viewer)
      ->withTargetPHIDs(array($target_phid))
      ->withConsumed(false)
      ->execute();

    $commands = msort($commands, 'getID');

    return $commands;
  }

  protected function checkLeaseExpiration(DrydockLease $lease) {
    $this->checkObjectExpiration($lease);
  }

  protected function checkResourceExpiration(DrydockResource $resource) {
    $this->checkObjectExpiration($resource);
  }

  private function checkObjectExpiration($object) {
    // Check if the resource or lease has expired. If it has, we're going to
    // send it a release command.

    // This command is sent from within the update worker so it is handled
    // immediately, but doing this generates a log and improves consistency.

    $expires = $object->getUntil();
    if (!$expires) {
      return;
    }

    $now = PhabricatorTime::getNow();
    if ($expires > $now) {
      return;
    }

    $viewer = $this->getViewer();
    $drydock_phid = id(new PhabricatorDrydockApplication())->getPHID();

    $command = DrydockCommand::initializeNewCommand($viewer)
      ->setTargetPHID($object->getPHID())
      ->setAuthorPHID($drydock_phid)
      ->setCommand(DrydockCommand::COMMAND_RELEASE)
      ->save();
  }

  protected function yieldIfExpiringLease(DrydockLease $lease) {
    if (!$lease->canUpdate()) {
      return;
    }

    $this->yieldIfExpiring($lease->getUntil());
  }

  protected function yieldIfExpiringResource(DrydockResource $resource) {
    if (!$resource->canUpdate()) {
      return;
    }

    $this->yieldIfExpiring($resource->getUntil());
  }

  private function yieldIfExpiring($expires) {
    if (!$expires) {
      return;
    }

    if (!$this->getTaskDataValue('isExpireTask')) {
      return;
    }

    $now = PhabricatorTime::getNow();
    throw new PhabricatorWorkerYieldException($expires - $now);
  }

}
