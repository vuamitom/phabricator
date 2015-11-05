<?php

final class AphrontFormTokenizerControl extends AphrontFormControl {

  private $datasource;
  private $disableBehavior;
  private $limit;
  private $placeholder;
  private $handles;
  private $originalValue;

  public function setDatasource(PhabricatorTypeaheadDatasource $datasource) {
    $this->datasource = $datasource;
    return $this;
  }

  public function setDisableBehavior($disable) {
    $this->disableBehavior = $disable;
    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-tokenizer';
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setPlaceholder($placeholder) {
    $this->placeholder = $placeholder;
    return $this;
  }

  public function setOriginalValue(array $original_value) {
    $this->originalValue = $original_value;
    return $this;
  }

  public function getOriginalValue() {
    return $this->originalValue;
  }

  public function willRender() {
    // Load the handles now so we'll get a bulk load later on when we actually
    // render them.
    $this->loadHandles();
  }

  protected function renderInput() {
    $name = $this->getName();

    $handles = $this->loadHandles();
    $handles = iterator_to_array($handles);

    if ($this->getID()) {
      $id = $this->getID();
    } else {
      $id = celerity_generate_unique_node_id();
    }

    $datasource = $this->datasource;
    if (!$datasource) {
      throw new Exception(
        pht('You must set a datasource to use a TokenizerControl.'));
    }
    $datasource->setViewer($this->getUser());

    $placeholder = null;
    if (!strlen($this->placeholder)) {
      $placeholder = $datasource->getPlaceholderText();
    }

    $values = nonempty($this->getValue(), array());
    $tokens = $datasource->renderTokens($values);

    foreach ($tokens as $token) {
      $token->setInputName($this->getName());
    }

    $template = id(new AphrontTokenizerTemplateView())
      ->setName($name)
      ->setID($id)
      ->setValue($tokens);

    $original_value = $this->getOriginalValue();
    if ($original_value !== null) {
      $template->setOriginalValue($original_value);
    }

    $username = null;
    if ($this->user) {
      $username = $this->user->getUsername();
    }

    $datasource_uri = $datasource->getDatasourceURI();
    $browse_uri = $datasource->getBrowseURI();
    if ($browse_uri) {
      $template->setBrowseURI($browse_uri);
    }

    if (!$this->disableBehavior) {
      Javelin::initBehavior('aphront-basic-tokenizer', array(
        'id' => $id,
        'src' => $datasource_uri,
        'value' => mpull($tokens, 'getValue', 'getKey'),
        'icons' => mpull($tokens, 'getIcon', 'getKey'),
        'types' => mpull($tokens, 'getTokenType', 'getKey'),
        'colors' => mpull($tokens, 'getColor', 'getKey'),
        'limit' => $this->limit,
        'username' => $username,
        'placeholder' => $placeholder,
        'browseURI' => $browse_uri,
      ));
    }

    return $template->render();
  }

  private function loadHandles() {
    if ($this->handles === null) {
      $viewer = $this->getUser();
      if (!$viewer) {
        throw new Exception(
          pht(
            'Call %s before rendering tokenizers. '.
            'Use %s on %s to do this easily.',
            'setUser()',
            'appendControl()',
            'AphrontFormView'));
      }

      $values = nonempty($this->getValue(), array());

      $phids = array();
      foreach ($values as $value) {
        if (!PhabricatorTypeaheadDatasource::isFunctionToken($value)) {
          $phids[] = $value;
        }
      }

      $this->handles = $viewer->loadHandles($phids);
    }

    return $this->handles;
  }

}
