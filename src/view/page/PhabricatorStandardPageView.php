<?php

/**
 * This is a standard Phabricator page with menus, Javelin, DarkConsole, and
 * basic styles.
 */
final class PhabricatorStandardPageView extends PhabricatorBarePageView
  implements AphrontResponseProducerInterface {

  private $baseURI;
  private $applicationName;
  private $glyph;
  private $menuContent;
  private $showChrome = true;
  private $classes = array();
  private $disableConsole;
  private $pageObjects = array();
  private $applicationMenu;
  private $showFooter = true;
  private $showDurableColumn = true;
  private $quicksandConfig = array();
  private $crumbs;
  private $navigation;

  public function setShowFooter($show_footer) {
    $this->showFooter = $show_footer;
    return $this;
  }

  public function getShowFooter() {
    return $this->showFooter;
  }

  public function setApplicationMenu($application_menu) {
    // NOTE: For now, this can either be a PHUIListView or a
    // PHUIApplicationMenuView.

    $this->applicationMenu = $application_menu;
    return $this;
  }

  public function getApplicationMenu() {
    return $this->applicationMenu;
  }

  public function setApplicationName($application_name) {
    $this->applicationName = $application_name;
    return $this;
  }

  public function setDisableConsole($disable) {
    $this->disableConsole = $disable;
    return $this;
  }

  public function getApplicationName() {
    return $this->applicationName;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function setShowChrome($show_chrome) {
    $this->showChrome = $show_chrome;
    return $this;
  }

  public function getShowChrome() {
    return $this->showChrome;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setPageObjectPHIDs(array $phids) {
    $this->pageObjects = $phids;
    return $this;
  }

  public function setShowDurableColumn($show) {
    $this->showDurableColumn = $show;
    return $this;
  }

  public function getShowDurableColumn() {
    $request = $this->getRequest();
    if (!$request) {
      return false;
    }

    $viewer = $request->getUser();
    if (!$viewer->isLoggedIn()) {
      return false;
    }

    $conpherence_installed = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorConpherenceApplication',
      $viewer);
    if (!$conpherence_installed) {
      return false;
    }

    if ($this->isQuicksandBlacklistURI()) {
      return false;
    }

    return true;
  }

  private function isQuicksandBlacklistURI() {
    $request = $this->getRequest();
    if (!$request) {
      return false;
    }

    $patterns = $this->getQuicksandURIPatternBlacklist();
    $path = $request->getRequestURI()->getPath();
    foreach ($patterns as $pattern) {
      if (preg_match('(^'.$pattern.'$)', $path)) {
        return true;
      }
    }
    return false;
  }

  public function getDurableColumnVisible() {
    $column_key = PhabricatorUserPreferences::PREFERENCE_CONPHERENCE_COLUMN;
    return (bool)$this->getUserPreference($column_key, 0);
  }

  public function addQuicksandConfig(array $config) {
    $this->quicksandConfig = $config + $this->quicksandConfig;
    return $this;
  }

  public function getQuicksandConfig() {
    return $this->quicksandConfig;
  }

  public function setCrumbs(PHUICrumbsView $crumbs) {
    $this->crumbs = $crumbs;
    return $this;
  }

  public function getCrumbs() {
    return $this->crumbs;
  }

  public function setNavigation(AphrontSideNavFilterView $navigation) {
    $this->navigation = $navigation;
    return $this;
  }

  public function getNavigation() {
    return $this->navigation;
  }

  public function getTitle() {
    $glyph_key = PhabricatorUserPreferences::PREFERENCE_TITLES;
    if ($this->getUserPreference($glyph_key) == 'text') {
      $use_glyph = false;
    } else {
      $use_glyph = true;
    }

    $title = parent::getTitle();

    $prefix = null;
    if ($use_glyph) {
      $prefix = $this->getGlyph();
    } else {
      $application_name = $this->getApplicationName();
      if (strlen($application_name)) {
        $prefix = '['.$application_name.']';
      }
    }

    if (strlen($prefix)) {
      $title = $prefix.' '.$title;
    }

    return $title;
  }


  protected function willRenderPage() {
    parent::willRenderPage();

    if (!$this->getRequest()) {
      throw new Exception(
        pht(
          'You must set the %s to render a %s.',
          'Request',
          __CLASS__));
    }

    $console = $this->getConsole();

    require_celerity_resource('phabricator-core-css');
    require_celerity_resource('phabricator-zindex-css');
    require_celerity_resource('phui-button-css');
    require_celerity_resource('phui-spacing-css');
    require_celerity_resource('phui-form-css');
    require_celerity_resource('phabricator-standard-page-view');
    require_celerity_resource('conpherence-durable-column-view');
    require_celerity_resource('font-lato');
    require_celerity_resource('font-aleo');

    Javelin::initBehavior('workflow', array());

    $request = $this->getRequest();
    $user = null;
    if ($request) {
      $user = $request->getUser();
    }

    if ($user) {
      $default_img_uri =
        celerity_get_resource_uri(
          'rsrc/image/icon/fatcow/document_black.png');
      $download_form = phabricator_form(
        $user,
        array(
          'action' => '#',
          'method' => 'POST',
          'class'  => 'lightbox-download-form',
          'sigil'  => 'download',
        ),
        phutil_tag(
          'button',
          array(),
          pht('Download')));

      Javelin::initBehavior(
        'lightbox-attachments',
        array(
          'defaultImageUri' => $default_img_uri,
          'downloadForm'    => $download_form,
        ));
    }

    Javelin::initBehavior('aphront-form-disable-on-submit');
    Javelin::initBehavior('toggle-class', array());
    Javelin::initBehavior('history-install');
    Javelin::initBehavior('phabricator-gesture');

    $current_token = null;
    if ($user) {
      $current_token = $user->getCSRFToken();
    }

    Javelin::initBehavior(
      'refresh-csrf',
      array(
        'tokenName' => AphrontRequest::getCSRFTokenName(),
        'header'    => AphrontRequest::getCSRFHeaderName(),
        'current'   => $current_token,
      ));

    Javelin::initBehavior('device');

    Javelin::initBehavior(
      'high-security-warning',
      $this->getHighSecurityWarningConfig());

    if ($console) {
      require_celerity_resource('aphront-dark-console-css');

      $headers = array();
      if (DarkConsoleXHProfPluginAPI::isProfilerStarted()) {
        $headers[DarkConsoleXHProfPluginAPI::getProfilerHeader()] = 'page';
      }
      if (DarkConsoleServicesPlugin::isQueryAnalyzerRequested()) {
        $headers[DarkConsoleServicesPlugin::getQueryAnalyzerHeader()] = true;
      }

      Javelin::initBehavior(
        'dark-console',
        $this->getConsoleConfig());

      // Change this to initBehavior when there is some behavior to initialize
      require_celerity_resource('javelin-behavior-error-log');
    }

    if ($user) {
      $viewer = $user;
    } else {
      $viewer = new PhabricatorUser();
    }

    $menu = id(new PhabricatorMainMenuView())
      ->setUser($viewer);

    if ($this->getController()) {
      $menu->setController($this->getController());
    }

    $application_menu = $this->getApplicationMenu();
    if ($application_menu) {
      if ($application_menu instanceof PHUIApplicationMenuView) {
        $crumbs = $this->getCrumbs();
        if ($crumbs) {
          $application_menu->setCrumbs($crumbs);
        }

        $application_menu = $application_menu->buildListView();
      }

      $menu->setApplicationMenu($application_menu);
    }

    $this->menuContent = $menu->render();
  }


  protected function getHead() {
    $monospaced = null;

    $request = $this->getRequest();
    if ($request) {
      $user = $request->getUser();
      if ($user) {
        $monospaced = $user->loadPreferences()->getPreference(
          PhabricatorUserPreferences::PREFERENCE_MONOSPACED);
      }
    }

    $response = CelerityAPI::getStaticResourceResponse();

    $font_css = null;
    if (!empty($monospaced)) {
      // We can't print this normally because escaping quotation marks will
      // break the CSS. Instead, filter it strictly and then mark it as safe.
      $monospaced = new PhutilSafeHTML(
        PhabricatorUserPreferences::filterMonospacedCSSRule(
          $monospaced));

      $font_css = hsprintf(
        '<style type="text/css">'.
        '.PhabricatorMonospaced, '.
        '.phabricator-remarkup .remarkup-code-block '.
          '.remarkup-code { font: %s !important; } '.
        '</style>',
        $monospaced);
    }

    return hsprintf(
      '%s%s%s',
      parent::getHead(),
      $font_css,
      $response->renderSingleResource('javelin-magical-init', 'phabricator'));
  }

  public function setGlyph($glyph) {
    $this->glyph = $glyph;
    return $this;
  }

  public function getGlyph() {
    return $this->glyph;
  }

  protected function willSendResponse($response) {
    $request = $this->getRequest();
    $response = parent::willSendResponse($response);

    $console = $request->getApplicationConfiguration()->getConsole();

    if ($console) {
      $response = PhutilSafeHTML::applyFunction(
        'str_replace',
        hsprintf('<darkconsole />'),
        $console->render($request),
        $response);
    }

    return $response;
  }

  protected function getBody() {
    $user = null;
    $request = $this->getRequest();
    if ($request) {
      $user = $request->getUser();
    }

    $header_chrome = null;
    if ($this->getShowChrome()) {
      $header_chrome = $this->menuContent;
    }

    $classes = array();
    $classes[] = 'main-page-frame';
    $developer_warning = null;
    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode') &&
        DarkConsoleErrorLogPluginAPI::getErrors()) {
      $developer_warning = phutil_tag_div(
        'aphront-developer-error-callout',
        pht(
          'This page raised PHP errors. Find them in DarkConsole '.
          'or the error log.'));
    }

    // Render the "you have unresolved setup issues..." warning.
    $setup_warning = null;
    if ($user && $user->getIsAdmin()) {
      $open = PhabricatorSetupCheck::getOpenSetupIssueKeys();
      if ($open) {
        $classes[] = 'page-has-warning';
        $setup_warning = phutil_tag_div(
          'setup-warning-callout',
          phutil_tag(
            'a',
            array(
              'href' => '/config/issue/',
              'title' => implode(', ', $open),
            ),
            pht('You have %d unresolved setup issue(s)...', count($open))));
      }
    }

    $main_page = phutil_tag(
      'div',
      array(
        'id' => 'phabricator-standard-page',
        'class' => 'phabricator-standard-page',
      ),
      array(
        $developer_warning,
        $header_chrome,
        $setup_warning,
        phutil_tag(
          'div',
          array(
            'id' => 'phabricator-standard-page-body',
            'class' => 'phabricator-standard-page-body',
          ),
          $this->renderPageBodyContent()),
      ));

    $durable_column = null;
    if ($this->getShowDurableColumn()) {
      $is_visible = $this->getDurableColumnVisible();
      $durable_column = id(new ConpherenceDurableColumnView())
        ->setSelectedConpherence(null)
        ->setUser($user)
        ->setQuicksandConfig($this->buildQuicksandConfig())
        ->setVisible($is_visible)
        ->setInitialLoad(true);
    }

    Javelin::initBehavior('quicksand-blacklist', array(
      'patterns' => $this->getQuicksandURIPatternBlacklist(),
    ));

    return phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $main_page,
        $durable_column,
      ));
  }

  private function renderPageBodyContent() {
    $console = $this->getConsole();

    $body = parent::getBody();

    $nav = $this->getNavigation();
    if ($nav) {
      $crumbs = $this->getCrumbs();
      if ($crumbs) {
        $nav->setCrumbs($crumbs);
      }
      $nav->appendChild($body);
      $body = phutil_implode_html('', array($nav->render()));
    } else {
      $crumbs = $this->getCrumbs();
      if ($crumbs) {
        $body = phutil_implode_html('', array($crumbs, $body));
      }
    }

    return array(
      ($console ? hsprintf('<darkconsole />') : null),
      $body,
      $this->renderFooter(),
    );
  }

  protected function getTail() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $tail = array(
      parent::getTail(),
    );

    $response = CelerityAPI::getStaticResourceResponse();

    if (PhabricatorEnv::getEnvConfig('notification.enabled')) {
      if ($user && $user->isLoggedIn()) {

        $client_uri = PhabricatorEnv::getEnvConfig('notification.client-uri');
        $client_uri = new PhutilURI($client_uri);
        if ($client_uri->getDomain() == 'localhost') {
          $this_host = $this->getRequest()->getHost();
          $this_host = new PhutilURI('http://'.$this_host.'/');
          $client_uri->setDomain($this_host->getDomain());
        }

        if ($request->isHTTPS()) {
          $client_uri->setProtocol('wss');
        } else {
          $client_uri->setProtocol('ws');
        }

        Javelin::initBehavior(
          'aphlict-listen',
          array(
            'websocketURI'  => (string)$client_uri,
          ) + $this->buildAphlictListenConfigData());
      }
    }

    $tail[] = $response->renderHTMLFooter();

    return $tail;
  }

  protected function getBodyClasses() {
    $classes = array();

    if (!$this->getShowChrome()) {
      $classes[] = 'phabricator-chromeless-page';
    }

    $agent = AphrontRequest::getHTTPHeader('User-Agent');

    // Try to guess the device resolution based on UA strings to avoid a flash
    // of incorrectly-styled content.
    $device_guess = 'device-desktop';
    if (preg_match('@iPhone|iPod|(Android.*Chrome/[.0-9]* Mobile)@', $agent)) {
      $device_guess = 'device-phone device';
    } else if (preg_match('@iPad|(Android.*Chrome/)@', $agent)) {
      $device_guess = 'device-tablet device';
    }

    $classes[] = $device_guess;

    if (preg_match('@Windows@', $agent)) {
      $classes[] = 'platform-windows';
    } else if (preg_match('@Macintosh@', $agent)) {
      $classes[] = 'platform-mac';
    } else if (preg_match('@X11@', $agent)) {
      $classes[] = 'platform-linux';
    }

    if ($this->getRequest()->getStr('__print__')) {
      $classes[] = 'printable';
    }

    if ($this->getRequest()->getStr('__aural__')) {
      $classes[] = 'audible';
    }

    $classes[] = 'phui-theme-'.PhabricatorEnv::getEnvConfig('ui.header-color');
    foreach ($this->classes as $class) {
      $classes[] = $class;
    }

    return implode(' ', $classes);
  }

  private function getConsole() {
    if ($this->disableConsole) {
      return null;
    }
    return $this->getRequest()->getApplicationConfiguration()->getConsole();
  }

  private function getConsoleConfig() {
    $user = $this->getRequest()->getUser();

    $headers = array();
    if (DarkConsoleXHProfPluginAPI::isProfilerStarted()) {
      $headers[DarkConsoleXHProfPluginAPI::getProfilerHeader()] = 'page';
    }
    if (DarkConsoleServicesPlugin::isQueryAnalyzerRequested()) {
      $headers[DarkConsoleServicesPlugin::getQueryAnalyzerHeader()] = true;
    }

    return array(
      // NOTE: We use a generic label here to prevent input reflection
      // and mitigate compression attacks like BREACH. See discussion in
      // T3684.
      'uri' => pht('Main Request'),
      'selected' => $user ? $user->getConsoleTab() : null,
      'visible'  => $user ? (int)$user->getConsoleVisible() : true,
      'headers' => $headers,
    );
  }

  private function getHighSecurityWarningConfig() {
    $user = $this->getRequest()->getUser();

    $show = false;
    if ($user->hasSession()) {
      $hisec = ($user->getSession()->getHighSecurityUntil() - time());
      if ($hisec > 0) {
        $show = true;
      }
    }

    return array(
      'show' => $show,
      'uri' => '/auth/session/downgrade/',
      'message' => pht(
        'Your session is in high security mode. When you '.
        'finish using it, click here to leave.'),
        );
  }

  private function renderFooter() {
    if (!$this->getShowChrome()) {
      return null;
    }

    if (!$this->getShowFooter()) {
      return null;
    }

    $items = PhabricatorEnv::getEnvConfig('ui.footer-items');
    if (!$items) {
      return null;
    }

    $foot = array();
    foreach ($items as $item) {
      $name = idx($item, 'name', pht('Unnamed Footer Item'));

      $href = idx($item, 'href');
      if (!PhabricatorEnv::isValidURIForLink($href)) {
        $href = null;
      }

      if ($href !== null) {
        $tag = 'a';
      } else {
        $tag = 'span';
      }

      $foot[] = phutil_tag(
        $tag,
        array(
          'href' => $href,
        ),
        $name);
    }
    $foot = phutil_implode_html(" \xC2\xB7 ", $foot);

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-standard-page-footer grouped',
      ),
      $foot);
  }

  public function renderForQuicksand() {
    parent::willRenderPage();
    $response = $this->renderPageBodyContent();
    $response = $this->willSendResponse($response);

    $extra_config = $this->getQuicksandConfig();

    return array(
      'content' => hsprintf('%s', $response),
    ) + $this->buildQuicksandConfig()
      + $extra_config;
  }

  private function buildQuicksandConfig() {
    $viewer = $this->getRequest()->getUser();
    $controller = $this->getController();

    $dropdown_query = id(new AphlictDropdownDataQuery())
      ->setViewer($viewer);
    $dropdown_query->execute();

    $rendered_dropdowns = array();
    $applications = array(
      'PhabricatorHelpApplication',
    );
    foreach ($applications as $application_class) {
      if (!PhabricatorApplication::isClassInstalledForViewer(
        $application_class,
        $viewer)) {
        continue;
      }
      $application = PhabricatorApplication::getByClass($application_class);
      $rendered_dropdowns[$application_class] =
        $application->buildMainMenuExtraNodes(
          $viewer,
          $controller);
    }

    $hisec_warning_config = $this->getHighSecurityWarningConfig();

    $console_config = null;
    $console = $this->getConsole();
    if ($console) {
      $console_config = $this->getConsoleConfig();
    }

    $upload_enabled = false;
    if ($controller) {
      $upload_enabled = $controller->isGlobalDragAndDropUploadEnabled();
    }

    $application_class = null;
    $application_search_icon = null;
    $controller = $this->getController();
    if ($controller) {
      $application = $controller->getCurrentApplication();
      if ($application) {
        $application_class = get_class($application);
        if ($application->getApplicationSearchDocumentTypes()) {
          $application_search_icon = $application->getFontIcon();
        }
      }
    }

    return array(
      'title' => $this->getTitle(),
      'aphlictDropdownData' => array(
        $dropdown_query->getNotificationData(),
        $dropdown_query->getConpherenceData(),
      ),
      'globalDragAndDrop' => $upload_enabled,
      'aphlictDropdowns' => $rendered_dropdowns,
      'hisecWarningConfig' => $hisec_warning_config,
      'consoleConfig' => $console_config,
      'applicationClass' => $application_class,
      'applicationSearchIcon' => $application_search_icon,
    ) + $this->buildAphlictListenConfigData();
  }

  private function buildAphlictListenConfigData() {
    $user = $this->getRequest()->getUser();
    $subscriptions = $this->pageObjects;
    $subscriptions[] = $user->getPHID();

    return array(
      'pageObjects'   => array_fill_keys($this->pageObjects, true),
      'subscriptions' => $subscriptions,
    );
  }

  private function getQuicksandURIPatternBlacklist() {
    $applications = PhabricatorApplication::getAllApplications();

    $blacklist = array();
    foreach ($applications as $application) {
      $blacklist[] = $application->getQuicksandURIPatternBlacklist();
    }

    return array_mergev($blacklist);
  }

  private function getUserPreference($key, $default = null) {
    $request = $this->getRequest();
    if (!$request) {
      return $default;
    }

    $user = $request->getUser();
    if (!$user) {
      return $default;
    }

    return $user->loadPreferences()->getPreference($key, $default);
  }

  public function produceAphrontResponse() {
    $controller = $this->getController();

    if (!$this->getApplicationMenu()) {
      $application_menu = $controller->buildApplicationMenu();
      if ($application_menu) {
        $this->setApplicationMenu($application_menu);
      }
    }

    $viewer = $this->getUser();
    if ($viewer && $viewer->getPHID()) {
      $object_phids = $this->pageObjects;
      foreach ($object_phids as $object_phid) {
        PhabricatorFeedStoryNotification::updateObjectNotificationViews(
          $viewer,
          $object_phid);
      }
    }

    if ($this->getRequest()->isQuicksand()) {
      $content = $this->renderForQuicksand();
      $response = id(new AphrontAjaxResponse())
        ->setContent($content);
    } else {
      $content = $this->render();
      $response = id(new AphrontWebpageResponse())
        ->setContent($content);
    }

    return $response;
  }

}
