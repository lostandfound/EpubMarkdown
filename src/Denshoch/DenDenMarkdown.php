<?php
namespace Denshoch;
#
# DenDenMarkdown - just a little help for them.
#
# DenDen Markdown
# Copyright (c) 2013-2017 Densho Channel
# <https://denshochan.com/>
#
# PHP Markdown Extra
# Copyright (c) 2004-2013 Michel Fortin
# <http://michelf.com/projects/php-markdown/>
#
# Original Markdown
# Copyright (c) 2004-2006 John Gruber
# <http://daringfireball.net/projects/markdown/>
#

class DenDenMarkdown extends \Michelf\MarkdownExtra
{

    const DENDENMARKDOWN_VERSION = "1.2.8";

    # Option for adding epub:type attribute.
    public $epubType = true;

    # Option for adding DPUB WAI-ARIA role attribute.
    public $dpubRole = true;

    # Optional class attributes for footnote links and backlinks.
    public $footnoteLinkClass = "noteref";
    public $footnoteLinkContent = "%%";
    public $footnoteBacklinkClass = "";
    public $footnoteBacklinkContent = "&#9166;";

    # Optional class attributes for optional headers.
    public $optionalheader_class = "bridgehead";

    # Optional class attributes for pagebreaks.
    public $pageNumberClass = "pagenum";
    public $pageNumberContent = "%%";

    # Optional class attributes for Harusame.
    public $autoTcy = false;
    public $tcyDigit = 2;
    public $autoTextOrientation = false;

    # Extra variables for ruby annotations
    public $rubyParenthesisOpen = "";
    public $rubyParenthesisClose = "";
    protected $rpOpen;
    protected $rpClose;

    # Extra variables for custom table markup
    public $ddmdTable = false;

    # Extra variables for endnotes
    public $ddmdEndnotes = false;
    public $endnoteLinkClass = "enref";
    public $endnoteLinkTitle = "";
    public $endnoteClass = "endnote";
    public $endnoteBacklinkClass = "";
    public $endnoteBacklinkContent = "&#9166;";
    protected $en_id_prefix = '';
    protected $endnotes = array();
    protected $endnotes_ordered = array();
    protected $endnotes_ref_count = array();
    protected $endnotes_numbers = array();
    protected $endnote_counter = 1;

    public function __construct(array $options = null)
    {
    #
    # Constructor function. Initialize the parser object.
    #

        $this->escape_chars .= '';

        $this->document_gamut += array(
            "stripEndnotes" => 16,
            "appendEndnotes"    => 51,
            );

        $this->block_gamut += array(
            "doBlockTitles"     => 11,
            "doDocBreaks"       => 20,
            );

        $this->span_gamut += array(
            "doEndnotes"         => 5,
            "doPageNums"         =>  9,
            "doRubies"           => 50,
            "doTcys"             => 50,
            );

        parent::__construct();

        if (false === is_null($options)){

            $intProps = [
                "tcyDigit"
            ];

            foreach ($intProps as $prop) {
                
                if ( array_key_exists( $prop, $options ) ) {

                    if ( is_int( $options[$prop] ) ) {

                        $this->$prop = $options[$prop];

                    } else {

                        trigger_error( "${prop} must be integer." );

                    }
                }

            }

            $boolProps = [
                "autoTcy",
                "autoTextOrientation",
                "epubType",
                "dpubRole",
                "ddmdTable",
                "ddmdEndnotes",
            ];

            foreach ($boolProps as $prop) {
                
                if ( array_key_exists( $prop, $options ) ) {

                    if ( is_bool( $options[$prop] ) ) {

                        $this->$prop = $options[$prop];

                    } else {

                        trigger_error( "${prop} must be boolean." );

                    }
                }

            }

            $stringProps = [
                "rubyParenthesisOpen",
                "rubyParenthesisClose",
                "footnoteLinkClass",
                "footnoteLinkContent",
                "footnoteBacklinkClass",
                "footnoteBacklinkContent",
                "endnoteLinkClass",
                "endnoteLinkTitle",
                "endnoteClass",
                "endnoteBacklinkClass",
                "endnoteBacklinkContent",
                "pageNumberClass",
                "pageNumberContent",
            ];

            foreach ($stringProps as $prop) {
                
                if ( array_key_exists( $prop, $options ) ) {
 
                    if ( is_string( $options[$prop] ) ) {

                        $this->$prop = $options[$prop];

                    } else {

                        trigger_error( "${prop} must be string." );

                    }
                }

            }

        }

        if ( $this->rubyParenthesisOpen !== "" && $this->rubyParenthesisClose !== "") {

            $this->rpOpen = "<rp>{$this->rubyParenthesisOpen}</rp>";
            $this->rpClose = "<rp>{$this->rubyParenthesisClose}</rp>";

        } else {

            $this->rpOpen = "";
            $this->rpClose = ""; 

        }
    }

    # Tags that are always treated as block tags:
    protected $block_tags_re = 'address|article|aside|blockquote|body|center|dd|details|dialog|dir|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hgroup|hr|html|legend|listing|menu|nav|ol|p|plaintext|pre|section|summary|style|table|ul|xmp';

    # Tags where markdown="1" default to span mode:
    protected $contain_span_tags_re = 'p|h[1-6]|li|dd|dt|td|th|legend|address';

    # Override transform()
    public function transform($text)
    {
        $text = \Denshoch\Utils::removeCtrlChars($text);

        $text = parent::transform($text);

        $harusame = new \Denshoch\Harusame(
            array(
                "autoTcy" => $this->autoTcy,
                "tcyDigit" => $this->tcyDigit,
                "autoTextOrientation" => $this->autoTextOrientation
            )
        );

        $text = $harusame->transform($text);

        /* Reset Endnotes count */
        $this->endnotes_ref_count = array();
        $this->endnotes_numbers = array();

        return $text;
    }

    protected function doBlockTitles($text)
    {
        # block titles:
        #   .BLOCK TITLE {#title1}
        #
        $text = preg_replace_callback('{
                ^(\.)       # $1 = string of \.
                [ ]*
                (.+?)       # $2 = TITLE text
                [ ]*
                (?:[ ]+ '.$this->id_class_attr_catch_re.' )?     # $3 = id/class attributes
                [ ]*
                \n+
            }xm',
            array(&$this, '_doBlockTitles_callback'), $text);

        return $text;
    }

    protected function _doBlockTitles_callback($matches) {
        $level = strlen($matches[1]);
        $dummy =& $matches[3];

        if($this->optionalheader_class != ""){
            $dummy .= ".$this->optionalheader_class";
        }
        $attr  = $this->doExtraAttributes("p", $dummy);
        if($this->epubType){
            $attr  .= " epub:type=\"bridgehead\"";
        }
        $block = "<p$attr><b>".$this->runSpanGamut($matches[2])."</b></p>";
        return "\n" . $this->hashBlock($block) . "\n\n";
    }

    protected function doDocBreaks($text)
    {
        return preg_replace(
            '{
                ^[ ]{0,3}       # Leading space
                (=)             # $1: First marker
                (?>             # Repeated marker group
                    [ ]{0,2}    # Zero, one, or two spaces.
                    \1          # Marker character
                ){2,}           # Group repeated at least twice
                [ ]*            # Tailing spaces
                $               # End of line.
            }mx',
            "\n".$this->hashBlock("<hr class=\"docbreak\"$this->empty_element_suffix")."\n",
            $text);
    }

    # GFM Hard Break
    protected function doHardBreaks($text)
    {
        # Do hard breaks:
        return preg_replace_callback('/ {0,}\n/',
            array($this, '_doHardBreaks_callback'), $text);
    }

    protected function doPageNums($text)
    {
        $pagebreak_block_reg = '/^[ ]{0,3}\[(%)(%?)(.+?)\][ ]*/m';
        $text = preg_replace_callback($pagebreak_block_reg, array(&$this, '_doPageNumsBlock_callback'), $text);

        $pagebreak_reg = '/\[(%)(%?)(.+?)\]/m';
        $text = preg_replace_callback($pagebreak_reg, array(&$this, '_doPageNums_callback'), $text);

        return $text;
    }

    protected function _doPageNumsBlock_callback($matches)
    {
        $title = $matches[3];

        if ("%" == $matches[2]) {
            $content = str_replace("%%", $title, $this->pageNumberContent);
        } else {
            $content = '';
        }
        $title = $this->encodeAttribute($title);

        $attr = "";
        $id = "pagenum_${title}";
        $attr .= " id=\"$id\"";
        if ($this->pageNumberClass != "") {
            $class = $this->pageNumberClass;
            $class = $this->encodeAttribute($class);
            $attr .= " class=\"$class\"";
        }
        $attr .= " title=\"$title\"";
        if($this->epubType) {
            $attr .= " epub:type=\"pagebreak\"";
        }

        if($this->dpubRole) {
            $attr .= " role=\"doc-pagebreak\"";
        }

        $result = "<div$attr>";
        $result .=  $content;
        $result .= "</div>";

        return $this->hashBlock($result);
    }

    protected function _doPageNums_callback($matches)
    {
        $title = $matches[3];

        if ("%" == $matches[2]) {
            $content = $title;
        } else {
            $content = '';
        }
        $title = $this->encodeAttribute($title);

        $attr = "";
        $id = "pagenum_$title";
        $attr .= " id=\"$id\"";
        if ($this->pageNumberClass != "") {
            $class = $this->pageNumberClass;
            $class = $this->encodeAttribute($class);
            $attr .= " class=\"$class\"";
        }
        $attr .= " title=\"$title\"";
        if($this->epubType) {
            $attr .= " epub:type=\"pagebreak\"";
        }

        if($this->dpubRole) {
            $attr .= " role=\"doc-pagebreak\"";
        }

        $result = "<span$attr>";
        $result .=  $content;
        $result .= "</span>";

        return $this->hashPart($result);
    }

    protected function doAutoLinks($text)
    {
        $text = preg_replace_callback('{<((https?|ftp|dict):[^\'">\s]+)>}i',
            array($this, '_doAutoLinks_url_callback'), $text);

        # Email addresses: <address@domain.foo>
        $text = preg_replace_callback('{
            <
            (?:mailto:)?
            (
                (?:
                    [-!#$%&\'*+/=?^_`.{|}~\w\x80-\xFF]+
                |
                    ".*?"
                )
                \@
                (?:
                    [-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
                |
                    \[[\d.a-fA-F:]+\]    # IPv4 & IPv6
                )
            )
            >
            }xi',
            array($this, '_doAutoLinks_email_callback'), $text);

        # Twitter account: <@twitter>
        $text = preg_replace_callback("/(?:<)(?<![0-9a-zA-Z'\"#@=:;])@([0-9a-zA-Z_]{1,15})(?:>)/u", array($this, '_doAutoLinks_twitter_callback'), $text);

        return $text;
    }

    protected function _doAutoLinks_twitter_callback($matches)
    {
        $account = $matches[1];
        $link = "<a href=\"https://twitter.com/$account\">@$account</a>";
        return $this->hashPart($link);
    }

    //split multibyte chars
    protected function mb_str_split($str, $enc, $length=1)
    {
        if ($length <= 0) {
            return false;
        }
        $result = array();
        for ($i = 0, $idx = 0;$i < mb_strlen($str, $enc);$i += $length) {
            $result[$idx++] = mb_substr($str, $i, $length, $enc);
        }
        return $result;
    }

    protected function doRubies($text)
    {
        $text = preg_replace_callback(
            '{
                ( (?<!\{) \{ )        # $1: Marker (not preceded by two /)
                (?=\S)                # Not followed by whitespace
                (?!\1)                #   or two others marker chars.
                (                     # $2: Base Text
                    (?>
                        [^|]+?        # Anthing not |.
                    )+?
                )
                \|
                (                     # $3: Ruby text

                    [^\}]+?
                )
                \}                    # End mark not preceded by whitespace.
            }sx',
            array($this,'doRubies_Callback'), $text);

        return $text;
    }

    protected function doRubies_Callback($matches)
    {
        $result = "<ruby>";
        $rbarray = $this->mb_str_split($matches[2], 'UTF-8');
        $rbcount = count($rbarray);
        $rtarray = explode("|", $matches[3]);
        $rtcount = count($rtarray);

        if ( $rbcount == $rtcount) {

            for ($i=0, $idx=0; $i < $rbcount; $i++) {
                $result = "${result}${rbarray[$idx]}{$this->rpOpen}<rt>${rtarray[$idx]}</rt>{$this->rpClose}";
                $idx++;
            }

            $result = $result."</ruby>";

        } else {

            $result = "${result}${matches[2]}{$this->rpOpen}<rt>".join('', $rtarray)."</rt>{$this->rpClose}</ruby>";

        }

        return $result;
    }

    protected function doTcys($text)
    {
        $text = preg_replace(
            '{
                ( (?<!\^) \^ )          # $1: Marker (not preceded by two /)
                (?=\S)                  # Not followed by whitespace
                (?!\1)                  #   or two others marker chars.
                (                       # $2: Content
                    (?>
                        [^\^]+?         #
                    |
                                        # Balence any regular / emphasis inside.
                        \/ (?=\S) (?! \^) (.+?) (?<=\S) \^
                    )+?
                )
                (?<=\S) \^              # End mark not preceded by whitespace.
            }sx',
            '<span class="tcy">\2</span>', $text);

        return $text;
    }

    //custum footnote code
    protected function appendFootnotes($text)
    {
    #
    # Append footnote list to text.
    #
        $text = preg_replace_callback('{F\x1Afn:(.*?)\x1A:}',
            array($this, '_appendFootnotes_callback'), $text);

        if (!empty($this->footnotes_ordered)) {
            $text .= "\n\n";
            $text .= "<div class=\"footnotes\"";

            if($this->epubType) {
                $text .= " epub:type=\"footnotes\"";
            }

            $text .= ">\n";
            $text .= "<hr". $this->empty_element_suffix ."\n";
            $text .= "<ol>\n\n";

            $attr = "";

            if ($this->footnoteBacklinkClass != "") {
                $class = $this->footnoteBacklinkClass;
                $class = $this->encodeAttribute($class);
                $attr .= " class=\"$class\"";
            }

            if ($this->fn_backlink_title != "") {
                $title = $this->fn_backlink_title;
                $title = $this->encodeAttribute($title);
                $attr .= " title=\"$title\"";
            }

            if ($this->dpubRole) {
                $attr .= " role=\"doc-backlink\"";
            }

            $num = 0;

            while (!empty($this->footnotes_ordered)) {
                $footnote = reset($this->footnotes_ordered);
                $note_id = key($this->footnotes_ordered);
                unset($this->footnotes_ordered[$note_id]);
                $ref_count = $this->footnotes_ref_count[$note_id];
                unset($this->footnotes_ref_count[$note_id]);
                unset($this->footnotes[$note_id]);

                $footnote .= "\n"; # Need to append newline before parsing.
                $footnote = $this->runBlockGamut("$footnote\n");
                $footnote = preg_replace_callback('{F\x1Afn:(.*?)\x1A:}',
                    array($this, '_appendFootnotes_callback'), $footnote);

                ++$num;
                $attr = str_replace("%%", $num, $attr);
                $note_id = $this->encodeAttribute($note_id);
                $content = str_replace("%%", $num, $this->footnoteBacklinkContent);

                # Prepare backlink, multiple backlinks if multiple references
                $backlink = "<a href=\"#fnref_${note_id}\"${attr}>${content}</a>";
                for ($ref_num = 2; $ref_num <= $ref_count; ++$ref_num) {
                    $backlink .= " <a href=\"#fnref$ref_num_${note_id}\"${attr}>${content}</a>";
                }
                # Add backlink to last paragraph; create new paragraph if needed.
                if (preg_match('{</p>$}', $footnote)) {
                    $footnote = substr($footnote, 0, -4) . "&#160;${backlink}</p>";
                } else {
                    $footnote .= "\n\n<p>${backlink}</p>";
                }

                $text .= "<li>\n";
                $text .= "<div id=\"fn_$note_id\" class=\"footnote\"";

                if($this->epubType){
                    $text .= " epub:type=\"footnote\"";
                }

                if($this->dpubRole){
                    $text .= " role=\"doc-footnote\"";
                }

                $text .= ">\n";
                $text .= $footnote . "\n";
                $text .= "</div>\n";
                $text .= "</li>\n\n";

            }

            $text .= "</ol>\n";
            $text .= "</div>\n";
        }
        return $text;
    }

    protected function _appendFootnotes_callback($matches)
    {
        $node_id = $this->fn_id_prefix . $matches[1];

        # Create footnote marker only if it has a corresponding footnote *and*
        # the footnote hasn't been used by another marker.
        if (isset($this->footnotes[$node_id])) {
            $num =& $this->footnotes_numbers[$node_id];
            if (!isset($num)) {
                # Transfer footnote content to the ordered list and give it its
                # number
                $this->footnotes_ordered[$node_id] = $this->footnotes[$node_id];
                $this->footnotes_ref_count[$node_id] = 1;
                $num = $this->footnote_counter++;
                $ref_count_mark = '';
            } else {
                $ref_count_mark = $this->footnotes_ref_count[$node_id] += 1;
            }

            $attr = "";
            $node_id = $this->encodeAttribute($node_id);
            $attr .= " id=\"fnref_$node_id\"";
            $attr .= " href=\"#fn_$node_id\"";
            $attr .= " rel=\"footnote\"";
            if ($this->footnoteLinkClass != "") {
                $class = $this->footnoteLinkClass;
                $class = $this->encodeAttribute($class);
                $attr .= " class=\"$class\"";
            }

            if ($this->fn_link_title != "") {
                $title = $this->fn_link_title;
                $title = $this->encodeAttribute($title);
                $attr .= " title=\"$title\"";
            }

            if ($this->epubType){
                $attr .= " epub:type=\"noteref\"";
            }

            if ($this->dpubRole){
                $attr .= " role=\"doc-noteref\"";
            }

            $attr = str_replace("%%", $num, $attr);
            $content = str_replace("%%", $num, $this->footnoteLinkContent);

            return
                "<a${attr}>${content}</a>"
                ;
        }

        return "[^".$matches[1]."]";
    }

    /* == Endnotes code == */

    /**
     * stripEndnotes
     *
     * Strips link definitions from text, stores the URLs and titles in
     * hash references.
     * 
     * @param $input string
     * @return string
     */
    protected function stripEndnotes($text) {

        if ( $this->ddmdEndnotes === false ) {
            return $text;
        }

        $less_than_tab = $this->tab_width - 1;

        # Link defs are in the form: [~id]: url "optional title"
        $text = preg_replace_callback('{
            ^[ ]{0,'.$less_than_tab.'}\[~(.+?)\][ ]?:  # note_id = $1
              [ ]*
              \n?                   # maybe *one* newline
            (                       # text = $2 (no blank lines allowed)
                (?:                 
                    .+              # actual text
                |
                    \n              # newlines but 
                    (?!\[.+?\][ ]?:\s)# negative lookahead for endnote or link definition marker.
                    (?!\n+[ ]{0,3}\S)# ensure line is not blank and followed 
                                    # by non-indented content
                )*
            )       
            }xm',
            array($this, '_stripEndnotes_callback'),
            $text);

        return $text;

    }

    /**
     * _stripEndnotes_callback
     * 
     * @param $matches array
     * @return string
     */
    protected function _stripEndnotes_callback($matches)
    {
        $note_id = $this->en_id_prefix . $matches[1];
        $this->endnotes[$note_id] = $this->outdent($matches[2]);

        return ''; # String that will replace the block
    }

    /**
     * doEndnotes
     * 
     * @param $input string
     * @return string
     */
    protected function doEndnotes($text)
    {
        if ( $this->ddmdEndnotes === false ) {
            return $text;
        }

        $text = preg_replace_callback('{
            (               # wrap whole match in $1
              \[
                ('.$this->nested_brackets_re.')     # link text = $2
              \]

              [ ]?              # one optional space
              (?:\n[ ]*)?       # one optional newline followed by spaces

              \[
                ~(.+?)       # id = $3
              \]

            )
            }xs', 
            array($this, '_doEndnotes_reference_callback'), $text);

        return $text;
    }

    protected function _doEndnotes_reference_callback($matches)
    {
        $whole_match = $matches[1];
        $link_text = $matches[2];
        $node_id = $matches[3];

        if (!$this->in_anchor) {
            return "E\x1Aen:${node_id}\x1A${link_text}\x1A:";
        }

        return $whole_match;
    }

    /**
     * appendEndnotes
     * 
     * @param $input string
     * @return string
     */
    protected function appendEndnotes($text)
    {
        if ( $this->ddmdEndnotes === false ) {
            return $text;
        }

        $text = preg_replace_callback('{E\x1Aen:(.*?)\x1A(.*?)\x1A:}', 
            array($this, '_appendEndnotes_callback'), $text);

        if ( !empty( $this->endnotes_ordered ) ) {
            $text .= "\n\n";
            $text .= "<div class=\"endnotes\"";
            if($this->epubType) {
                $text .= " epub:type=\"endnotes\"";
            }
            if($this->dpubRole) {
                $text .=" role=\"doc-endnotes\"";
            }
            $text .= ">\n";
            $text .= "<hr". $this->empty_element_suffix ."\n\n";

            $attr = "";

            if ( $this->endnoteBacklinkClass !== "" ) {
                $attr .= " class=\"{$this->endnoteBacklinkClass}\"";
            }

            if ( $this->dpubRole ) {
                $attr .= " role=\"doc-backlink\"";
            }

            $num = 0;

            while ( !empty($this->endnotes_ordered ) ) {

                $endnote = reset( $this->endnotes_ordered );
                $note_id = key( $this->endnotes_ordered );
                unset( $this->endnotes_ordered[$note_id] );
                $ref_count = $this->endnotes_ref_count[$note_id];
                unset( $this->endnotes_ref_count[$note_id] );
                unset( $this->endnotes[$note_id] );

                $endnote .= "\n";
                $endnote = $this->runBlockGamut("$endnote\n");
                $endnote = preg_replace_callback('{E\x1Aen:(.*?)\x1A(.*?)\x1A:}', 
                    array($this, '_appendEndnotes_callback'), $endnote);

                $attr = str_replace("%%", ++$num, $attr);
                $note_id = $this->encodeAttribute($note_id);

                $backlink = "<a href=\"#enref:$note_id\"$attr>{$this->endnoteBacklinkContent}</a>";

                for ($ref_num = 2; $ref_num <= $ref_count; ++$ref_num) {
                    $backlink .= " <a href=\"#enref$ref_num:$note_id\"$attr>{$this->endnoteBacklinkContent}</a>";
                }

                # Add backlink to last paragraph; create new paragraph if needed.
                if (preg_match('{</p>$}', $endnote)) {
                    $endnote = substr($endnote, 0, -4) . "&#160;${backlink}</p>";
                } else {
                    $endnote .= "\n\n<p>${backlink}</p>";
                }

                $text .= "<div id=\"en:$note_id\"";
                if ($this->endnoteClass !== "") {
                    $text .= " class=\"{$this->endnoteClass}\"";
                }
                if ($this->epubType) {
                    $text .= " epub:type=\"endnote\"";
                }
                if ($this->dpubRole) {
                    $text .= " role=\"doc-endnote\"";
                }
                $text .= ">\n";
                $text .= $endnote . "\n";
                $text .= "</div>\n\n";

            }

            $text .= "</div>";
        }

        return $text;
    }

    /**
     * _appendEndnotes_callback
     * リンク元
     * 
     * @param $matches array
     * @return string
     */
    protected function _appendEndnotes_callback($matches)
    {

        $node_id = $this->en_id_prefix . $matches[1];
        $link_text = $matches[2];

        # Create endnote marker only if it has a corresponding endnote *and*
        # the end hasn't been used by another marker.
        if (isset($this->endnotes[$node_id])) {

            $num =& $this->endnotes_numbers[$node_id];

            if (!isset($num)) {

                # Transfer footnote content to the ordered list and give it its
                # number
                $this->endnotes_ordered[$node_id] = $this->endnotes[$node_id];
                $this->endnotes_ref_count[$node_id] = 1;
                $num = $this->endnote_counter++;
                $ref_count_mark = '';

            } else {

                $ref_count_mark = $this->endnotes_ref_count[$node_id] += 1;

            }

            $attr = "";

            if ($this->endnoteLinkClass != "") {
                $class = $this->endnoteLinkClass;
                $class = $this->encodeAttribute($class);
                $attr .= " class=\"$class\"";
            }

            if ($this->endnoteLinkTitle != "") {
                $title = $this->endnoteLinkTitle;
                $title = $this->encodeAttribute($title);
                $attr .= " title=\"$title\"";
            }

            if ($this->epubType) {
                $attr .= " epub:type=\"noteref\"";
            }

            if ($this->dpubRole) {
                $attr .= " role=\"doc-noteref\"";
            }

            $attr = str_replace("%%", $num, $attr);
            $node_id = $this->encodeAttribute($node_id);

            return "<a id=\"enref${ref_count_mark}:${node_id}\" href=\"#en:${node_id}\"${attr}>${link_text}</a>";

        }

        return "[${matches[2]}][~${matches[1]}]";

    }
}
