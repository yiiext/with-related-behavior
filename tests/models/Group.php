<?php
class Group extends CActiveRecord
{
	public $otherName;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'group';
	}

	public function rules()
	{
		return array(
			array('name','required'),
			array('otherName','required','on'=>'scenario'),
			array('otherName','length','max'=>5,'on'=>'scenario'),
		);
	}

	protected function beforeSave()
	{
		if(!parent::beforeSave())
			return false;

		if($this->isAttributeSafe('otherName'))
			$this->name=$this->otherName;

		return true;
	}
}
