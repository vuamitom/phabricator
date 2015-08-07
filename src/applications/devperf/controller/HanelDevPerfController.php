<?php 
abstract class HanelDevPerfController extends PhabricatorController {
    
    public function buildSideNavView() {

        $user = $this->getRequest()->getUser(); 
        
        $classes = id(new PhutilSymbolLoader())
            ->setAncestorClass('HanelDevPerfController')
            ->loadObjects();
        $classes = msort($classes, 'getName');
        
        $nav = new AphrontSideNavFilterView();
        $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

        foreach ($classes as $class => $obj) {
            $name = $obj->getName();
            $nav->addFilter($class, $name);
        }


//        id(new PhabricatorCommitSearchEngine())
//            ->setViewer($user)
//            ->addNavigationItems($nav->getMenu()); 
//
//        $nav->selectFilter(null); 

        return $nav; 
    }

    public function buildApplicationMenu() {
        return $this->buildSideNavView()->getMenu();
    }
}
?>
