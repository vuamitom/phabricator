<?php

abstract class HeraldController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('Create Herald Rule'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('fa-plus-square'));

    return $crumbs;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('new', pht('Create Rule'));
    }

    id(new HeraldRuleSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav
      ->addLabel(pht('Utilities'))
      ->addFilter('test', pht('Test Console'))
      ->addFilter('transcript', pht('Transcripts'));

    $nav->selectFilter(null);

    return $nav;
  }

}
