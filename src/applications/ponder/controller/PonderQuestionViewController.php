<?php

final class PonderQuestionViewController extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needAnswers(true)
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $question_xactions = $this->buildQuestionTransactions($question);
    $answers = $this->buildAnswers($question->getAnswers());

    $authors = mpull($question->getAnswers(), null, 'getAuthorPHID');
    if (isset($authors[$viewer->getPHID()])) {
      $answer_add_panel = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(
          pht(
            'You have already answered this question. You can not answer '.
            'twice, but you can edit your existing answer.'));
    } else {
      $answer_add_panel = new PonderAddAnswerView();
      $answer_add_panel
        ->setQuestion($question)
        ->setUser($viewer)
        ->setActionURI('/ponder/answer/add/');
    }

    $header = new PHUIHeaderView();
    $header->setHeader($question->getTitle());
    $header->setUser($viewer);
    $header->setPolicyObject($question);

    if ($question->getStatus() == PonderQuestionStatus::STATUS_OPEN) {
      $header->setStatus('fa-square-o', 'bluegrey', pht('Open'));
    } else {
      $text = PonderQuestionStatus::getQuestionStatusFullName(
        $question->getStatus());
      $icon = PonderQuestionStatus::getQuestionStatusIcon(
        $question->getStatus());
      $header->setStatus($icon, 'dark', $text);
    }

    $actions = $this->buildActionListView($question);
    $properties = $this->buildPropertyListView($question, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addTextCrumb('Q'.$id, '/Q'.$id);

    $ponder_view = phutil_tag(
      'div',
      array(
        'class' => 'ponder-question-view',
      ),
      array(
        $crumbs,
        $object_box,
        $question_xactions,
        $answers,
        $answer_add_panel,
      ));

    return $this->buildApplicationPage(
      array(
        $ponder_view,
      ),
      array(
        'title' => 'Q'.$question->getID().' '.$question->getTitle(),
        'pageObjects' => array_merge(
          array($question->getPHID()),
          mpull($question->getAnswers(), 'getPHID')),
      ));
  }

  private function buildActionListView(PonderQuestion $question) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $id = $question->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $question,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($question)
      ->setObjectURI($request->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Question'))
        ->setHref($this->getApplicationURI("/question/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($question->getStatus() == PonderQuestionStatus::STATUS_OPEN) {
      $name = pht('Close Question');
      $icon = 'fa-check-square-o';
    } else {
      $name = pht('Reopen Question');
      $icon = 'fa-square-o';
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($name)
        ->setIcon($icon)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit)
        ->setHref($this->getApplicationURI("/question/status/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-list')
        ->setName(pht('View History'))
        ->setHref($this->getApplicationURI("/question/history/{$id}/")));

    return $view;
  }

  private function buildPropertyListView(
    PonderQuestion $question,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($question)
      ->setActionList($actions);

    $view->addProperty(
      pht('Author'),
      $viewer->renderHandle($question->getAuthorPHID()));

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($question->getDateCreated(), $viewer));

    $view->invokeWillRenderEvent();

    $view->addSectionHeader(pht('Question'));
    $view->addTextContent(
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          PhabricatorMarkupEngine::renderOneObject(
            $question,
            $question->getMarkupField(),
            $viewer)),
      ));


    return $view;
  }

  private function buildQuestionTransactions(PonderQuestion $question) {
    $viewer = $this->getViewer();
    $id = $question->getID();

    $timeline = $this->buildTransactionTimeline(
      $question,
      id(new PonderQuestionTransactionQuery())
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT)));
    $xactions = $timeline->getTransactions();

    $add_comment = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($question->getPHID())
      ->setShowPreview(false)
      ->setHeaderText(pht('Question Comment'))
      ->setAction($this->getApplicationURI("/question/comment/{$id}/"))
      ->setSubmitButtonName(pht('Comment'));

    return $this->wrapComments(
      count($xactions),
      array(
        $timeline,
        $add_comment,
      ));
  }

  /**
   * This is fairly non-standard; building N timelines at once (N = number of
   * answers) is tricky business.
   *
   * TODO - re-factor this to ajax in one answer panel at a time in a more
   * standard fashion. This is necessary to scale this application.
   */
  private function buildAnswers(array $answers) {
    $viewer = $this->getViewer();

    $out = array();

    $xactions = id(new PonderAnswerTransactionQuery())
      ->setViewer($viewer)
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
      ->withObjectPHIDs(mpull($answers, 'getPHID'))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $xaction_groups = mgroup($xactions, 'getObjectPHID');

    foreach ($answers as $answer) {
      $author_phid = $answer->getAuthorPHID();
      $xactions = idx($xaction_groups, $answer->getPHID(), array());
      $id = $answer->getID();

      $out[] = phutil_tag('br');
      $out[] = phutil_tag('br');
      $out[] = id(new PhabricatorAnchorView())
        ->setAnchorName("A$id");
      $header = id(new PHUIHeaderView())
        ->setHeader($viewer->renderHandle($author_phid));

      $actions = $this->buildAnswerActions($answer);
      $properties = $this->buildAnswerProperties($answer, $actions);

      $object_box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->addPropertyList($properties);

      $out[] = $object_box;
      $details = array();

      $details[] = id(new PhabricatorApplicationTransactionView())
        ->setUser($viewer)
        ->setObjectPHID($answer->getPHID())
        ->setTransactions($xactions)
        ->setMarkupEngine($engine);

      $form = id(new PhabricatorApplicationTransactionCommentView())
        ->setUser($viewer)
        ->setObjectPHID($answer->getPHID())
        ->setShowPreview(false)
        ->setHeaderText(pht('Answer Comment'))
        ->setAction($this->getApplicationURI("/answer/comment/{$id}/"))
        ->setSubmitButtonName(pht('Comment'));

      $details[] = $form;

      $out[] = $this->wrapComments(
        count($xactions),
        $details);
    }

    $out[] = phutil_tag('br');
    $out[] = phutil_tag('br');

    return $out;
  }

  private function buildAnswerActions(PonderAnswer $answer) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $id = $answer->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $answer,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($answer)
      ->setObjectURI($request->getRequestURI());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Answer'))
        ->setHref($this->getApplicationURI("/answer/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-list')
        ->setName(pht('View History'))
        ->setHref($this->getApplicationURI("/answer/history/{$id}/")));

    return $view;
  }

  private function buildAnswerProperties(
    PonderAnswer $answer,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($answer)
      ->setActionList($actions);

    $view->addProperty(
      pht('Created'),
      phabricator_datetime($answer->getDateCreated(), $viewer));

    $view->invokeWillRenderEvent();

    $view->addSectionHeader(pht('Answer'));
    $view->addTextContent(
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          PhabricatorMarkupEngine::renderOneObject(
            $answer,
            $answer->getMarkupField(),
            $viewer)),
      ));

    return $view;
  }

  private function wrapComments($n, $stuff) {
    if ($n == 0) {
      $text = pht('Add a Comment');
    } else {
      $text = pht('Show %s Comments', new PhutilNumber($n));
    }

    $show_id = celerity_generate_unique_node_id();
    $hide_id = celerity_generate_unique_node_id();

    Javelin::initBehavior('phabricator-reveal-content');
    require_celerity_resource('ponder-view-css');

    $show = phutil_tag(
      'div',
      array(
        'id' => $show_id,
        'class' => 'ponder-show-comments',
      ),
      javelin_tag(
        'a',
        array(
          'href' => '#',
          'sigil' => 'reveal-content',
          'meta' => array(
            'showIDs' => array($hide_id),
            'hideIDs' => array($show_id),
          ),
        ),
        $text));

    $hide = phutil_tag(
      'div',
      array(
        'class' => 'ponder-comments-view',
        'id' => $hide_id,
        'style' => 'display: none',
      ),
      $stuff);

    return array($show, $hide);
  }

}
