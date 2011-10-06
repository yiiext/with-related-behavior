<?php
class WithRelatedBehaviorTest extends CDbTestCase
{
	public $fixtures=array(
		'articles'=>'Article',
		'comments'=>'Comment',
		'groups'=>'Group',
		'tags'=>'Tag',
		'users'=>'User',
		':article_tag',
	);

	public function testSave()
	{
		$article=new Article;

		$user=new User;

		$article->user=$user;

		$user->group=new Group;

		$comment1=new Comment;
		$comment1->user=$user;

		$comment2=new Comment;
		$comment2->user=$user;

		$article->comments=array($comment1,$comment2);

		$tag1=new Tag;
		$tag2=new Tag;

		$article->tags=array($tag1,$tag2);

		$result=$article->withRelated->save(true,array(
			'user'=>array('group'),
			'comments'=>array('user'),
			'tags',
		));

		$this->assertFalse($result);

		$article->title='article1';
		$user->name='user1';
		$user->group->name='group1';
		$comment1->content='comment1';
		$comment2->content='comment2';
		$tag1->name='tag1';
		$tag2->name='tag2';

		$result=$article->withRelated->save(true,array(
			'user'=>array('group'),
			'comments'=>array('user'),
			'tags',
		));

		$this->assertTrue($result);

		$article=Article::model()->with(array(
			'user'=>array(
				'with'=>'group',
				'alias'=>'article_user',
			),
			'comments'=>array(
				'with'=>array(
					'user'=>array(
						'alias'=>'comment_user',
					),
				),
			),
			'tags',
		))->find();

		$this->assertNotNull($article);
		$this->assertEquals('article1',$article->title);
		$this->assertNotNull($article->user);
		$this->assertEquals('user1',$article->user->name);
		$this->assertNotNull($article->user->group);
		$this->assertEquals('group1',$article->user->group->name);
		$this->assertEquals(2,count($article->comments));
		$this->assertEquals('comment1',$article->comments[0]->content);
		$this->assertEquals('comment2',$article->comments[1]->content);
		$this->assertNotNull($article->comments[0]->user);
		$this->assertEquals('user1',$article->comments[0]->user->name);
		$this->assertNotNull($article->comments[1]->user);
		$this->assertEquals('user1',$article->comments[1]->user->name);
		$this->assertEquals(2,count($article->tags));
		$this->assertEquals('tag1',$article->tags[0]->name);
		$this->assertEquals('tag2',$article->tags[1]->name);

		$article=Article::model()->with('comments')->find();

		$comments=$article->comments;
		$comments[0]->content='comment1 update';
		$comments[1]->content='comment2 update';

		$comment=new Comment;
		$comment->user=$user;
		$comment->content='comment3';

		$comments[]=$comment;

		$comment=new Comment;
		$comment->user=$user;
		$comment->content='comment4';

		$comments[]=$comment;

		$article->comments=$comments;

		$result=$article->withRelated->save(true,array('comments'=>array('user')));
		$this->assertTrue($result);

		$article=Article::model()->with('comments')->find();
		$this->assertEquals(4,count($article->comments));
		$this->assertEquals('comment1 update',$article->comments[0]->content);
		$this->assertEquals('comment2 update',$article->comments[1]->content);
		$this->assertEquals('comment3',$article->comments[2]->content);
		$this->assertEquals('comment4',$article->comments[3]->content);

		$article=Article::model()->with('tags')->find();

		$tags=$article->tags;
		$tags[0]->name='tag1 update';
		$tags[1]->name='tag2 update';

		$tag=new Tag;
		$tag->name='tag3';

		$tags[]=$tag;

		$tag=new Tag;
		$tag->name='tag4';

		$tags[]=$tag;

		$article->tags=$tags;

		$result=$article->withRelated->save(true,array('tags'));
		$this->assertTrue($result);

		$article=Article::model()->with('tags')->find();
		$this->assertEquals(4,count($article->tags));
		$this->assertEquals('tag1 update',$article->tags[0]->name);
		$this->assertEquals('tag2 update',$article->tags[1]->name);
		$this->assertEquals('tag3',$article->tags[2]->name);
		$this->assertEquals('tag4',$article->tags[3]->name);
	}
}