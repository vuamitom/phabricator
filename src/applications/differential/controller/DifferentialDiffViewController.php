<?php

final class DifferentialDiffViewController extends DifferentialController {

  private $id;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$diff) {
      return new Aphront404Response();
    }

    if ($diff->getRevisionID()) {
      return id(new AphrontRedirectResponse())
        ->setURI('/D'.$diff->getRevisionID().'?id='.$diff->getID());
    }

    $diff_phid = $diff->getPHID();
    $buildables = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($diff_phid))
      ->withManualBuildables(false)
      ->needBuilds(true)
      ->needTargets(true)
      ->execute();
    $buildables = mpull($buildables, null, 'getBuildablePHID');
    $diff->attachBuildable(idx($buildables, $diff_phid));

    // TODO: implement optgroup support in AphrontFormSelectControl?
    $select = array();
    $select[] = hsprintf('<optgroup label="%s">', pht('Create New Revision'));
    $select[] = phutil_tag(
      'option',
      array('value' => ''),
      pht('Create a new Revision...'));
    $select[] = hsprintf('</optgroup>');

    $selected_id = $request->getInt('revisionID');

    $revisions = $this->loadSelectableRevisions($viewer, $selected_id);

    if ($revisions) {
      $select[] = hsprintf(
        '<optgroup label="%s">',
        pht('Update Existing Revision'));
      foreach ($revisions as $revision) {
        if ($selected_id == $revision->getID()) {
          $selected = 'selected';
        } else {
          $selected = null;
        }

        $select[] = phutil_tag(
          'option',
          array(
            'value' => $revision->getID(),
            'selected' => $selected,
          ),
          id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(128)
          ->truncateString(
            'D'.$revision->getID().' '.$revision->getTitle()));
      }
      $select[] = hsprintf('</optgroup>');
    }

    $select = phutil_tag(
      'select',
      array('name' => 'revisionID'),
      $select);

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->setAction('/differential/revision/edit/')
      ->addHiddenInput('diffID', $diff->getID())
      ->addHiddenInput('viaDiffView', 1)
      ->addHiddenInput(
        id(new DifferentialRepositoryField())->getFieldKey(),
        $diff->getRepositoryPHID())
      ->appendRemarkupInstructions(
        pht(
          'Review the diff for correctness. When you are satisfied, either '.
          '**create a new revision** or **update an existing revision**.'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setLabel(pht('Attach To'))
        ->setValue($select))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Continue')));

    $props = id(new DifferentialDiffProperty())->loadAllWhere(
    'diffID = %d',
      $diff->getID());
    $props = mpull($props, 'getData', 'getName');

    $property_head = id(new PHUIHeaderView())
      ->setHeader(pht('Properties'));

    $property_view = new PHUIPropertyListView();

    $changesets = $diff->loadChangesets();
    $changesets = msort($changesets, 'getSortKey');

    $table_of_contents = id(new DifferentialDiffTableOfContentsView())
      ->setChangesets($changesets)
      ->setVisibleChangesets($changesets)
      ->setCoverageMap($diff->loadCoverageMap($viewer));

    $refs = array();
    foreach ($changesets as $changeset) {
      $refs[$changeset->getID()] = $changeset->getID();
    }

    $details = id(new DifferentialChangesetListView())
      ->setChangesets($changesets)
      ->setVisibleChangesets($changesets)
      ->setRenderingReferences($refs)
      ->setStandaloneURI('/differential/changeset/')
      ->setDiff($diff)
      ->setTitle(pht('Diff %d', $diff->getID()))
      ->setUser($request->getUser());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Diff %d', $diff->getID()));

    $prop_box = id(new PHUIObjectBoxView())
      ->setHeader($property_head)
      ->addPropertyList($property_view)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $prop_box,
        $table_of_contents,
        $details,
      ),
      array(
        'title' => pht('Diff View'),
      ));
  }

  private function loadSelectableRevisions(
    PhabricatorUser $viewer,
    $selected_id) {

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withAuthors(array($viewer->getPHID()))
      ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();
    $revisions = mpull($revisions, null, 'getID');

    // If a specific revision is selected (for example, because the user is
    // following the "Update Diff" workflow), but not present in the dropdown,
    // try to add it to the dropdown even if it is closed. This allows the
    // workflow to be used to update abandoned revisions.

    if ($selected_id) {
      if (empty($revisions[$selected_id])) {
        $selected = id(new DifferentialRevisionQuery())
          ->setViewer($viewer)
          ->withAuthors(array($viewer->getPHID()))
          ->withIDs(array($selected_id))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->execute();
        $revisions = mpull($selected, null, 'getID') + $revisions;
      }
    }

    return $revisions;
  }


}
