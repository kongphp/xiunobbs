<?php

/*
 * Copyright (C) xiuno.com
 */

/*
	thread_type 功能中的 data, 用来记录 typeidsum，此表可能会膨胀。
*/

class thread_type_data extends base_model {
	
	function __construct(&$conf) {
		parent::__construct($conf);
		$this->table = 'thread_type_data';
		$this->primarykey = array('fid', 'tid', 'typeidsum');
		
	}
	
	// 一个主题属于多个 typeid
	public function xcreate($fid, $tid, $typeid1 = 0, $typeid2 = 0, $typeid3 = 0) {
		$typeid1 && $this->_create($fid, $tid, $typeid1);
		$typeid2 && $this->_create($fid, $tid, $typeid2);
		$typeid3 && $this->_create($fid, $tid, $typeid3);
		$typeid1 && $typeid2 && $this->_create($fid, $tid, $typeid1 + $typeid2);
		$typeid1 && $typeid3 && $this->_create($fid, $tid, $typeid1 + $typeid3);
		$typeid2 && $typeid3 && $this->_create($fid, $tid, $typeid2 + $typeid3);
		$typeid1 && $typeid2 && $typeid3 && $this->_create($fid, $tid, $typeid1 + $typeid2 + $typeid3);
	}
	
	public function _create($fid, $tid, $typeidsum) {
		$arr = array(
			'fid'=>$fid,
			'tid'=>$tid,
			'typeidsum'=>$typeidsum,
		);
		if($this->create($arr)) {
			$this->thread_type_count->inc($fid, $typeidsum);
			return $arr;
		} else {
			return FALSE;
		}
	}
	
	// 重新调整主题属于哪些分类
	public function xupdate($fid, $tid, $typeid1 = 0, $typeid2 = 0, $typeid3 = 0) {
		$this->xdelete($fid, $tid);
		$this->xcreate($fid, $tid, $typeid1, $typeid2, $typeid3);
	}

	public function _delete($fid, $tid, $typeidsum) {
		$return = $this->delete($fid, $tid, $typeidsum);
		$this->thread_type_count->dec($fid, $typeidsum);
		return $return;
	}
	
	// 删除主题所属的 typeid
	public function xdelete($fid, $tid) {
		$typelist = $this->get_list_by_fid_tid($fid, $tid);
		foreach($typelist as $type) {
			$this->delete($fid, $tid, $type['typeidsum']);
		}
		return TRUE;
	}
	
	public function delete_by_fid($fid) {
		return $this->index_delete(array('fid'=>$fid));
	}
	
	// 获取一个主题属于哪些 typeid 组合，用来删除
	public function get_list_by_fid_tid($fid, $tid) {
		$typelist = $this->index_fetch(array('fid'=>$fid, 'tid'=>$tid), array(), 0, 100);
		return $typelist;
	}
	
	// orderby tid
	public function get_threadlist_by_fid($fid, $typeidsum, $start, $limit) {
		$threadlist = array();
		$tidlist = $this->index_fetch(array('fid'=>$fid, 'typeidsum'=>$typeidsum), array('tid'=>-1), $start, $limit);
		foreach($tidlist as $v) {
			$threadlist[$v['tid']] = $this->thread->read($v['fid'], $v['tid']);
		}
		return $threadlist;
	}
	
	// 获取一个主题属于哪些 typeid，用来编辑
	public function get_typeidarr_by_fid_tid($fid, $tid) {
		$typeidarr = array();
		$tidlist = $this->index_fetch(array('fid'=>$fid, 'tid'=>$tid), array(), 0, 100);
		foreach($tidlist as $v) {
			if($v['typeidsum'] < 41) $typeidarr[] = $v['typeidsum'];
		}
		return $typeidarr;
	}
	
}
?>