<?php
class Tag extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'tag';
	}

	public function relations()
	{
		return array(
			'createdBy'=>array(self::BELONGS_TO,'User','created_by_id'),
		);
	}

	public function rules()
	{
		return array(
			array('name','required'),
		);
	}
}