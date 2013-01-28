<?php
class User extends CActiveRecord
{
	public $firstName;
	public $lastName;
	private $_age;

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

			array('age','required','on'=>'scenario2'),
			array('age','numerical','min'=>16,'on'=>'scenario2'),
		);
	}

	public function behaviors()
	{
		return array(
			'withRelated'=>'ext.WithRelatedBehavior',
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

	public function getAge()
	{
		if($this->_age===null)
		{
			$this->_age=18;
		}
		return $this->_age;
	}

	public function setAge($age)
	{
		$this->_age=$age;
	}
}
