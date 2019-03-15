<?php

class Simple_TOC {

    public $content;
    public $dom;

    public $hTags;
    public $headingOutline;
    public $tree;
    public $html;
    public $curNode;
    public $prevNode;

    public $prevLevel;
    public $curLevel;
    public $parents;

    public $headingElems;
    public $count;

    public function __construct( $content ) {
        $this->content = $content;
        $this->dom = new DomDocument();

        $this->hTags = [ 
            "h1" => 1, 
            "h2" => 2, 
            "h3" => 3, 
            "h4" => 4, 
            "h5" => 5, 
            "h6" => 6 
        ];
        $this->headingOutline = [];
        $this->tree = [];
        $this->html = '';
        $this->curNode = [];
        $this->prevNode = [];

        $this->prevLevel = 2;
        $this->parents = [null];
    }

    public function sort_headings() {
        for( $i=0; $i<$this->count-1; $i++ ) {
            $j = $i + 1;
            for( $j = $i+1; $j<$this->count; $j++ ) {
                if( $this->headingElems[$i]->getLineNo() > $this->headingElems[$j]->getLineNo() ) {
                    $temp = $this->headingElems[$i];
                    $this->headingElems[$i] = $this->headingElems[$j];
                    $this->headingElems[$j] = $temp;
                }
            }
        }
    }

    public function create_outline() {
        $prevLevel = $this->hTags[$this->headingElems[0]->tagName];
        $parents_initial = [
            'tag' => null,
            'level' => null,
            'text' => null,
            'id' => null,
            'parentId' => null
        ];
        $parents = [$parents_initial];
        for( $i=0; $i<$this->count; $i++ ) {
            $tag = $this->headingElems[$i]->tagName;
            $tag = strtolower($tag);
            $curLevel = $this->hTags[$tag];

            $id = null;

            if( !($id = $this->headingElems[$i]->getAttribute('id')) ) {
                $id = $tag . '_' . ($i+1);
                $this->headingElems[$i]->setAttribute( 'id', $id );
            }

            if( $i === 0 ) {
                $parentId = $id;
            }

            $curNode = [
                'tag' => $tag,
                'level' => $curLevel,
                'text' => $this->headingElems[$i]->textContent,
                'id' => $id,
                'parentId' => null
            ];

            if( $curLevel < $prevLevel ) {
                $diff = $prevLevel - $curLevel;
                for( $j=0; $j<$diff; $j++ ) {
                    if( $curLevel <= end($parents)['level'] && count($parents) > 0 ) {
                        array_pop($parents);
                    }
                }
            }
            else if( ($curLevel > $prevLevel) && ($i !== 0) ) {
                array_push($parents, $prevNode);
            }

            $curNode['parentId'] = $parents[count($parents)-1]['id'];
            $this->headingOutline[$id] = $curNode;

            $prevLevel = $curLevel;
            $prevNode = $curNode;
        }
    }

    public function create_tree($parent = null) {
        $children = [];
        foreach($this->headingOutline as $h) {
            //$h = $this->headingOutline[$i];
            if( $parent === $h['parentId']) {
                $c = [
                    'id' => $h['id'], 
                    'name' => $h['text'],
                    'children' => $this->create_tree($h['id'])
                ];
                $children[] = $c;
            }
        }
        return $children;
    }

    public function generate_html($tree, $depth = '') {
        $this->html .= '<ul>';
        $prev_level = null;
        $i=0;
        foreach( $tree as $t ) {

            $i++;

            $t_ho = $this->headingOutline[$t['id']];
            $parent_id = $t_ho['parentId'];
            $parent_level = $this->headingOutline[$parent_id]['level'];
            $level = $t_ho['level'];

            $l_diff = $level - $parent_level;

            $this->html .= '<li>';

            for( $k = 0; $k < $l_diff-1; $k++ ) {
                $this->html .= '<ul><li>';
            }
            $this->html .= '<a href="#' . $t['id'] . '">';
            $depth = ltrim($depth, ".");
            if( !empty($depth) ) {
                $this->html .= $depth . '.';
            }
            $this->html .= $i . '. ' . $t['name'];
            $this->html .= '</a>';
           
            if( count($t['children']) > 0 ) {
                $this->generate_html($t['children'], $depth.'.'.$i);
            }
            for( $k = 0; $k < $l_diff-1; $k++ ) {
                $this->html .= '</li></ul>';  
            }

            $this->html .= '</li>';
            $prev_level = $level;
        }
        $this->html .= '</ul>';
    }

    public function get_headings() {
        @$this->dom->loadHTML($this->content);

        $h = [];

        foreach( $this->hTags as $k => $v ) {
            $h[] = $this->dom->getElementsByTagName($k);
        }

        foreach ( $h as $v ) {
            $count = $v->length;
            for( $i=0; $i<$count; $i++ ) {
                $node = $v->item($i);
                $this->headingElems[] = $node;
            }
        }
        $this->count = count($this->headingElems);
        $this->sort_headings();
        $this->create_outline();
        $this->tree = $this->create_tree();
        $this->generate_html($this->tree);
        $wrapper_begin = '<div class="simple-toc">';
        $wrapper_begin .= '<div class="simple-toc-title">Table of Contents</div>';
        $wrapper_begin .= '<nav>';
        $wrapper_end = '</nav>';
        $wrapper_end .= '</div>';
        $this->html = $wrapper_begin . $this->html . $wrapper_end;
    }

    public function toc() {
        if( empty ($this->content) ) {
            return '';
        }
        $this->get_headings();
        $this->content = @$this->dom->saveHTML();
        return [$this->html, $this->content];
    }
}