<?php

abstract class DiffusionView extends AphrontView {

  private $diffusionRequest;

  final public function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  final public function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  final public function linkHistory($path) {
    $href = $this->getDiffusionRequest()->generateURI(
      array(
        'action' => 'history',
        'path'   => $path,
      ));

    return javelin_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'diffusion-link-icon',
        'sigil' => 'has-tooltip',
        'meta' => array(
          'tip' => pht('History'),
          'align' => 'E',
        ),
      ),
      id(new PHUIIconView())->setIconFont('fa-list-ul blue'));
  }

  final public function linkBrowse($path, array $details = array()) {
    require_celerity_resource('diffusion-icons-css');
    Javelin::initBehavior('phabricator-tooltips');

    $file_type = idx($details, 'type');
    unset($details['type']);

    $display_name = idx($details, 'name');
    unset($details['name']);

    if (strlen($display_name)) {
      $display_name = phutil_tag(
        'span',
        array(
          'class' => 'diffusion-browse-name',
        ),
        $display_name);
    }

    if (isset($details['external'])) {
      $href = id(new PhutilURI('/diffusion/external/'))
        ->setQueryParams(
          array(
            'uri' => idx($details, 'external'),
            'id'  => idx($details, 'hash'),
          ));
      $tip = pht('Browse External');
    } else {
      $href = $this->getDiffusionRequest()->generateURI(
        $details + array(
          'action' => 'browse',
          'path'   => $path,
        ));
      $tip = pht('Browse');
    }

    $icon = DifferentialChangeType::getIconForFileType($file_type);
    $icon_view = id(new PHUIIconView())->setIconFont("{$icon} blue");

    // If we're rendering a file or directory name, don't show the tooltip.
    if ($display_name !== null) {
      $sigil = null;
      $meta = null;
    } else {
      $sigil = 'has-tooltip';
      $meta = array(
        'tip' => $tip,
        'align' => 'E',
      );
    }

    return javelin_tag(
      'a',
      array(
        'href' => $href,
        'class' => 'diffusion-link-icon',
        'sigil' => $sigil,
        'meta' => $meta,
      ),
      array(
        $icon_view,
        $display_name,
      ));
  }

  final public static function nameCommit(
    PhabricatorRepository $repository,
    $commit) {

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $commit_name = substr($commit, 0, 12);
        break;
      default:
        $commit_name = $commit;
        break;
    }

    $callsign = $repository->getCallsign();
    return "r{$callsign}{$commit_name}";
  }

  final public static function linkCommit(
    PhabricatorRepository $repository,
    $commit,
    $summary = '') {

    $commit_name = self::nameCommit($repository, $commit);
    $callsign = $repository->getCallsign();

    if (strlen($summary)) {
      $commit_name .= ': '.$summary;
    }

    return phutil_tag(
      'a',
      array(
        'href' => "/r{$callsign}{$commit}",
      ),
      $commit_name);
  }

  final public static function linkRevision($id) {
    if (!$id) {
      return null;
    }

    return phutil_tag(
      'a',
      array(
        'href' => "/D{$id}",
      ),
      "D{$id}");
  }

  final public static function renderName($name) {
    $email = new PhutilEmailAddress($name);
    if ($email->getDisplayName() && $email->getDomainName()) {
      Javelin::initBehavior('phabricator-tooltips', array());
      require_celerity_resource('aphront-tooltip-css');
      return javelin_tag(
        'span',
        array(
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip'   => $email->getAddress(),
            'align' => 'E',
            'size'  => 'auto',
          ),
        ),
        $email->getDisplayName());
    }
    return hsprintf('%s', $name);
  }

}
