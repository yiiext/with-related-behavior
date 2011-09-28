<?php
class Group extends CActiveRecord
{
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
		);
	}
}
