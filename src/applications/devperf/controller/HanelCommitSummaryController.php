<?php 
final class HanelCommitSummaryController extends HanelDevPerfController {
    
    public function shouldAllowPublic() {
        return true;
    }
    
    public function getName() {
        return 'Commit';        
    }

    public function handleRequest(AphrontRequest $request) {
        $controller = id(new PhabricatorApplicationSearchController())
            ->setQueryKey($request->getURIData('queryKey'))
            ->setSearchEngine(new PhabricatorCommitSearchEngine())
            ->setNavigation($this->buildSideNavView()); 

        return $this->delegateToController($controller);
    }
}
?>
