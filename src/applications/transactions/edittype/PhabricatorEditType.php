<?php

abstract class PhabricatorEditType extends Phobject {

  private $editType;
  private $transactionType;
  private $field;
  private $description;
  private $summary;
  private $metadata = array();

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    if ($this->summary === null) {
      return $this->getDescription();
    }
    return $this->summary;
  }

  public function setField(PhabricatorEditField $field) {
    $this->field = $field;
    return $this;
  }

  public function getField() {
    return $this->field;
  }

  public function setEditType($edit_type) {
    $this->editType = $edit_type;
    return $this;
  }

  public function getEditType() {
    return $this->editType;
  }

  public function setMetadata($metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  public function getMetadata() {
    return $this->metadata;
  }

  public function setTransactionType($transaction_type) {
    $this->transactionType = $transaction_type;
    return $this;
  }

  public function getTransactionType() {
    return $this->transactionType;
  }

  abstract public function generateTransaction(
    PhabricatorApplicationTransaction $template,
    array $spec);

  abstract public function getValueType();
  abstract public function getValueDescription();

}
