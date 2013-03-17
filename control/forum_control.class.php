<?php

/*
 * Copyright (C) xiuno.com
 */

!defined('FRAMEWORK_PATH') && exit('FRAMEWORK_PATH not defined.');

include BBS_PATH.'control/common_control.class.php';

class forum_control extends common_control {
	
	function __construct(&$conf) {
		parent::__construct($conf);
		$this->_checked['bbs'] = ' class="checked"';
	}
	
	// 列表
	public function on_index() {
		
		// hook forum_index_before.php
		
		// 主题分类, typeid 将决定 fid，优先级高于 fid
		$typeid1 = intval(core::gpc('typeid1'));
		$typeid2 = intval(core::gpc('typeid2'));
		$typeid3 = intval(core::gpc('typeid3'));
		$typeidsum = $typeid1 + $typeid2 + $typeid3;
		
		$this->_checked['typecate'] = $this->_checked['threadtype'] = array();
		empty($typeid1) ? $this->_checked['typecates'][1] = ' class="checked"' :  $this->_checked['types'][$typeid1] = ' class="checked"';
		empty($typeid2) ? $this->_checked['typecates'][2] = ' class="checked"' :  $this->_checked['types'][$typeid2] = ' class="checked"';
		empty($typeid3) ? $this->_checked['typecates'][3] = ' class="checked"' :  $this->_checked['types'][$typeid3] = ' class="checked"';
		
		// fid
		$fid = intval(core::gpc('fid'));
		$forum = $this->mcache->read('forum', $fid);
		$this->check_forum_exists($forum);
		$this->check_forum_status($forum);
		$this->check_access($forum, 'read');
		
		// orderby
		$orderby = core::gpc('orderby', 'C');
		$orderby = $orderby === NULL ? $forum['orderby'] : intval($orderby);
		$this->_checked['orderby'][$orderby] = ' checked';
		
		$this->_title[] = $forum['seo_title'] ? $forum['seo_title'] : $forum['name'];
		$this->_seo_keywords = $forum['seo_keywords'] ?  $forum['seo_keywords'] : $forum['name'];
		
		// hook forum_index_page_before.php
		
		$pagesize = $this->conf['forum_index_pagesize'];
		$page = misc::page();
		misc::setcookie($this->conf['cookie_pre'].'page', $page, $_SERVER['time'] + 86400 * 7, $this->conf['cookie_path'], $this->conf['cookie_domain']);
		
		// hook forum_index_get_list_before.php
		
		$start = ($page - 1) * $pagesize;
		$limit = $pagesize;
		$threads = $typeidsum ? $this->thread_type_count->get_threads($fid, $typeidsum) : $forum['threads'];
		
		if($typeidsum > 0) {
			$threadlist = $this->thread_type_data->get_threadlist_by_fid($fid, $typeidsum, $start, $limit);
		} else {
			$threadlist = $this->thread->get_threadlist_by_fid($fid, $orderby, $start, $limit);
		}
		
		$toplist = $page == 1 && empty($typeidsum) ? $this->get_toplist($forum) : array();
		$toplist = array_filter($toplist);
		$threadlist = array_diff_key($threadlist, $toplist);
		$threadlist = array_filter($threadlist);
		
		// 点击次数
		$readtids = '';
		foreach($toplist as &$thread) {
			$readtids .= ','.$thread['tid'];
			// 获取置顶版块
			$topforum = $this->mcache->read('forum', $thread['fid']);
			$this->thread->format($thread, $topforum);
		}
		foreach($threadlist as $k=>&$thread) {
			if($thread['top'] > 0) {
				unset($threadlist[$k]);
				continue;
			}
			$readtids .= ','.$thread['tid'];
			$this->thread->format($thread, $forum);
		}
		$readtids = substr($readtids, 1); 
		$click_server = $this->conf['click_server']."?db=tid&r=$readtids";
		// hook forum_index_get_list_after.php
		$typeidadd = $typeidsum > 0 ? "-typeid1-$typeid1-typeid2-$typeid2-typeid3-$typeid3" : '';
		$pages = misc::pages("?forum-index-fid-$fid$typeidadd.htm", $threads, $page, $pagesize);
		$ismod = $this->is_mod($forum, $this->_user);
		$this->view->assign('fid', $fid);
		$this->view->assign('typeid1', $typeid1);
		$this->view->assign('typeid2', $typeid2);
		$this->view->assign('typeid3', $typeid3);
		$this->view->assign('forum', $forum);
		$this->view->assign('page', $page);
		$this->view->assign('pages', $pages);
		$this->view->assign('limit', $limit);
		$this->view->assign('toplist', $toplist);
		$this->view->assign('threadlist', $threadlist);
		$this->view->assign('ismod', $ismod);
		$this->view->assign('orderby', $orderby);
		$this->view->assign('click_server', $click_server);
		// hook forum_index_after.php
		$this->view->display('forum_index.htm');
	}
		
	// 所有版块，考虑权限！
	public function on_list() {
		$this->_checked['forum_list'] = ' class="checked"';
		
		$forumarr = $this->conf['forumarr'];
		$threadlists = $this->runtime->get('threadlists');
		if(empty($threadlists)) {
			foreach($forumarr as $fid=>$name) {
				if(!empty($forumarr[$fid])) {
					$access = $this->forum_access->read($fid, $this->_user['groupid']);
					if(!empty($access) && !$access['allowread']) {
						unset($forumarr[$fid]);
						continue;
					}
				}
				$threadlist = $this->thread->get_threadlist_by_fid($fid, 0, 0, 10);
				foreach($threadlist as &$thread) {
					$thread['dateline_fmt'] = misc::minidate($thread['dateline']);
				}
				$threadlists[$fid] = $threadlist;
			}
			$this->runtime->set('threadlists', $threadlists, 60); // todo:一分钟的缓存时间！这里可以根据负载进行调节。
		}
		$this->view->assign('forumarr', $forumarr);
		$this->view->assign('threadlists', $threadlists);
		$this->view->display('forum_list.htm');
	}
	
	private function get_toplist($forum) {
		$fidtids = array();
		// 3 级置顶
		$fidtids = $this->get_fidtids($this->conf['toptids']);
		
		// 1 级置顶
		$fidtids += $this->get_fidtids($forum['toptids']);
		$toplist = $this->thread->mget($fidtids);
		
		return $toplist;
	}
	
	// index_control.class copyed
	private function get_fidtids($s) {
		$fidtids = array();
		if($s) {
			$fidtidlist = explode(' ', trim($s));
			foreach($fidtidlist as $fidtid) {
				list($fid, $tid) = explode('-', $fidtid);
				$fidtids["$fid-$tid"] = array($fid, $tid);
			}
		}
		return $fidtids;
	}
	
	//hook forum_control_after.php
}

?>