<?php
class User extends CActiveRecord
{
	public $firstName;
	public $lastName;

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'user';
	}

	public function relations()
	{
		return array(
			'group'=>array(self::BELONGS_TO,'Group','group_id'),
		);
	}

	public function rules()
	{
		return array(
			array('name','required'),
			array('firstName,lastName','required','on'=>'scenario'),
			array('firstName,lastName','length','min'=>3,'max'=>7,'on'=>'scenario'),
		);
	}

	public function behaviors()
	{
		return array(
			'withRelated'=>'WithRelatedBehavior',
		);
	}

	protected function beforeSave()
	{
		if(!parent::beforeSave())
			return false;

		if($this->isAttributeSafe('firstName') && $this->isAttributeSafe('lastName'))
			$this->name=$this->firstName.' '.$this->lastName;

		return true;
	}
}