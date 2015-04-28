<?php
final class HanelDevPerfApplication extends PhabricatorApplication {
    
    public function getBaseURI() {
        return '/devperf/';
    }

    public function getName() {
        return pht('Dev Performance');
    } 

    public function getShortDescription() {
        return pht('Commit Statistics of Each Developer');
    }

    public function getApplicationGroup() {
        return self::GROUP_ADMIN; 
    }

    public function getFontIcon() {
        return 'fa-check-circle-o';
    }

    public function getApplicationOrder() {
        return 0.2;
    }

    public function getRoutes() {
        return array(
            '/devperf/' => array(                
                '(?:query/(?P<queryKey>[^/]+)/)?' => 'HanelCommitSummaryController',
            ),
        );
    }
    
    public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
        return array(
            array(
              'name' => pht('Audit User Guide'),
              'href' => PhabricatorEnv::getDoclink('Audit User Guide'),
            ),
        );
    }
}
?>
