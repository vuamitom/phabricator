<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  private $project;
  protected $parentProject; 

  protected function getParentProject(){
    if ($this->parentProject == NULL){
      $this->parentProject = ProjectHelper::getParentProject($this->project, $this->getViewer());
      $this->parentProject = $this->parentProject ? $this->parentProject : false; 
    }
    return $this->parentProject;
  }

  protected function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  protected function getProject() {
    return $this->project;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  public function buildSideNavView($for_app = false) {
    $project = $this->getProject();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $viewer = $this->getViewer();

    $id = null;
    if ($for_app) {
      if ($project) {
        $id = $project->getID();
        $nav->addFilter("profile/{$id}/", pht('Profile'));
        $nav->addFilter("board/{$id}/", pht('Workboard'));
        $nav->addFilter("members/{$id}/", pht('Members'));
        $nav->addFilter("feed/{$id}/", pht('Feed'));
        $nav->addFilter("details/{$id}/", pht('Edit Details'));
      }
      $nav->addFilter('create', pht('Create Project'));
    }

    if (!$id) {
      id(new PhabricatorProjectSearchEngine())
        ->setViewer($viewer)
        ->addNavigationItems($nav->getMenu());
    }

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildIconNavView(PhabricatorProject $project) {
    $this->setProject($project);
    $viewer = $this->getViewer();
    $id = $project->getID();
    $picture = $project->getProfileImageURI();
    $name = $project->getName();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      $board_icon = 'fa-columns';
    } else {
      $board_icon = 'fa-columns grey';
    }

    // check if project is module/sub project
    $hss = PhabricatorEnv::getEnvConfig('hss.enable-extension');
    $parentProject = false;
    if ($hss){
      $parentProject = $this->getParentProject();
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setIconNav(true);
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    if (!$parentProject){
      $nav->addIcon("profile/{$id}/", $name, null, $picture);    
    }
    $nav->addIcon("board/{$id}/", pht('Workboard'), $board_icon);

    $class = 'PhabricatorManiphestApplication';
    
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $phid = $project->getPHID();
      $query_uri = urisprintf(
        '/maniphest/?statuses=open()&projects=%s#R',
        $phid);
      if (!$hss){
        $nav->addIcon(null, pht('Open Tasks'), 'fa-anchor', null, $query_uri);
      }
      else{
        $nav->addIcon("maniphest/{$id}/", pht('Open Tasks'), 'fa-anchor'); 
      }
    }

    if (!$hss){
      $nav->addIcon("feed/{$id}/", pht('Feed'), 'fa-newspaper-o');
      $nav->addIcon("members/{$id}/", pht('Members'), 'fa-group');    
      $nav->addIcon("details/{$id}/", pht('Edit Details'), 'fa-pencil');
    }
    else{
      $nav->addIcon("report/{$id}/", pht('Report'), 'fa-line-chart');
      //TODO: move it to event listener
      $nav->addIcon("modules/{$id}/", pht('Modules'), 'fa-sitemap');
      $nav->addIcon("repositories/{$id}/", pht('Repositories'), 'fa-database');
      $nav->addIcon("activities/{$id}/", pht('Feed'), 'fa-newspaper-o');      
      if ($parentProject){
        $parent_id = $parentProject->getID();
        $nav->addIcon("modules/edit/{$parent_id}/{$id}", pht('Edit Details'), 'fa-pencil');
      }
      else{
        $nav->addIcon("members/{$id}/", pht('Members'), 'fa-group');    
        $nav->addIcon("details/{$id}/", pht('Edit Details'), 'fa-pencil');
      }
    }

    return $nav;
  }

}
