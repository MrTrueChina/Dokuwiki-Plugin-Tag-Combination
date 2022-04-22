<?php

/**
 * Copyright 2022 Mr.true.China

 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at

 *     http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use \dokuwiki\Extension\Plugin;

/**
 * helper 组件，相当于其他地方的 库、前置、核心
 */
class helper_plugin_tagcombination extends Plugin
{
    /**
     * 存储关联信息的文件的路径
     */
    public static $dataFilePath;

    /**
     * 加载指定标签的组合信息
     * 
     * @param string $tag
     * @return string 组合信息
     */
    public function loadTagCombinationInfo($tag)
    {
        // 没有传入标签则返回空串
        if (!$tag) {
            return '';
        }

        // 加载组合信息
        $combinationInfos = $this->loadCombinationInfo();

        // 没有记录下这个标签的组合信息，返回空串
        if (!array_key_exists($tag, $combinationInfos)) {
            return '';
        }

        // 返回组合信息
        return $combinationInfos[$tag];
    }

    /**
     * 编辑指定标签的组合信息
     * 
     * @param string $tag 要编辑组合信息的标签
     * @param string $combinationInfo 组合信息
     */
    public function editTagCombinationInfo($tag, $combinationInfo)
    {
        dbglog('编辑标签组合信息，标签 = ' . $tag . '，组成标签 = ', $combinationInfo);

        // 如果没有传入标签，不处理
        if (!$tag) {
            return;
        }

        // 加载组合信息
        $combinationInfos = $this->loadCombinationInfo();

        // 把标签的组合信息设为传入的信息
        $combinationInfos[$tag] = $combinationInfo;

        // 保存组合信息
        $this->saveCombinationInfo($combinationInfos);
    }

    /**
     * 从文件中加载组合信息
     * 
     * @return array 所有标签的组合信息数组
     */
    public function loadCombinationInfo()
    {
        // 没有这个文件则直接返回空数组
        if (!file_exists(helper_plugin_tagcombination::$dataFilePath)) {
            return array();
        }

        // 从文件里读取内容
        $combinationInfoJson = file_get_contents(helper_plugin_tagcombination::$dataFilePath);

        // dbglog('从文件中加载到的组合信息文本 = '. $combinationInfoJson);

        // 没有获取到信息则返回空数组
        if (!$combinationInfoJson) {
            return array();
        }

        // dbglog('组合信息转换出的对象 = ');
        // dbglog(json_decode($combinationInfoJson));

        // 返回解码获得的数组对象
        return json_decode($combinationInfoJson, true, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 向文件中保存组合信息
     * 
     * @param array $combinationInfos 所有标签的组合信息数组，格式是 [标签名=>组合信息, 标签名=>组合信息]
     */
    public function saveCombinationInfo($combinationInfos)
    {
        // 如果传入的组合信息不是数组，直接返回，防止覆盖了正确的数据
        if (!is_array($combinationInfos)) {
            return;
        }

        file_put_contents(helper_plugin_tagcombination::$dataFilePath, json_encode($combinationInfos, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 通过标签和标签命名空间获取使用到这些标签和组合标签的页面的可以供 PageList 显示的页面信息，可以使用页面命名空间限制显示的页面<br/>
     * 支持的语法：<br/>
     * Tag1 Tag2：搜索包含 Tag1 和 Tag2 中至少一个的页面<br/>
     * Tag1 +Tag2：搜索包含 Tag1 且包含 Tag2 的页面<br/>
     * Tag1 -Tag2：搜索包含 Tag1 但不包含 Tag2 的页面
     * 
     * @param string $tags 标签和标签命名空间的字符串，支持语法
     * @param string $namespace 页面命名空间，使用这个参数将会只返回这个命名空间下的页面信息
     * @return array 可供 PageList 插进显示的页面信息列表
     */
    public function getPagesInfosByCombinedTags($tags, $namespace)
    {
        // 加载 tagFilter 插件的 syntax 组件，如果没加载到则发出需要 tagFilter 插件的 log，返回空数组
        if (!$tagFilterSyntax = $this->loadHelper('tagfilter_syntax')) {
            dbglog($this->getLang('needTagFilterPlugin'));
            return array();
        }
        // 加载 Tag 插件的 helper 组件，如果没加载到则发出需要 Tag 插件的 log，返回空数组
        if (!$tagHelper = $this->loadHelper('tag')) {
            dbglog($this->getLang('needTagPlugin'));
            return array();
        }
        // 保存到 $this 里，方便后面递归的时候使用
        $this->tagHelper = $tagHelper;

        // 把标签字符串拆分为标签数组，依次进行：把换行改空格 -> 分割标签 -> 去除前后空格 -> 去除空元素
        $tagsArray = array_filter(array_map('trim', explode(' ', str_replace('\n', ' ', $tags))));

        dbglog('$tagsArray = ');
        dbglog($tagsArray);

        // 准备一个列表存储搜索到的页面
        $pageIds = array();

        // 遍历拆分后的标签
        foreach ($tagsArray as $tag) {
            if (substr($tag, 0, 1) === '+') {
                // 这个标签以 + 开头，表示这个标签必须使用到，取交集
                $pageIds = array_intersect($pageIds, $this->getPagesIdsByCombinedTag(substr($tag, 1), $namespace));
            } else if (substr($tag, 0, 1) === '-') {
                // 这个标签以 - 开头，表示排除这个标签，取差集
                $pageIds = array_diff($pageIds, $this->getPagesIdsByCombinedTag(substr($tag, 1), $namespace));
            } else {
                // 其他开头，表示可以含有这个标签或含有其他标签，取并集
                $pageIds = array_unique(array_merge($pageIds, $this->getPagesIdsByCombinedTag($tag, $namespace)));
            }
        }

        // 去重
        $pageIds = array_unique($pageIds);

        // 通过 TagFilter 的方法把页面 ID 转为页面信息
        $pagesInfos = $tagFilterSyntax->prepareList($pageIds, ['tagimagecolumn' => array()]);

        return $pagesInfos;
    }

    /**
     * 通过标签或标签命名空间获取使用到标签和组合标签的页面的ID，可以使用页面命名空间限制显示的页面
     * 
     * @param string $tag 标签或标签命名空间的名字
     * @param string $namespace 页面命名空间，使用这个参数将会只返回这个命名空间下的页面信息
     * @param array $searchedTags 已经查询过的标签，这些标签会被忽略以防止无限循环
     * @return array 页面 ID 列表
     */
    public function getPagesIdsByCombinedTag($tag, $namespace, $searchedTags = array())
    {
        // 如果这个标签在已经搜索的标签列表里，不进行重复搜索直接返回空数组
        if (in_array($tag, $searchedTags)) {
            return array();
        }

        // 获取传入的标签的组成部分标签
        $combinedTags = $this->getCombinedTagsByTag($tag);
        // 以传入的标签作为命名空间，查找在这个命名空间下的所有标签
        $subTags = $this->getTagsByTagNamespace($tag);

        // 用传入的标签名作为标签，查询使用了这个标签的页面
        $pageIds = $this->tagHelper->_tagIndexLookup(array($tag));

        dbglog('获取单个标签 ' . $tag . ' 的页面 = ');
        dbglog($pageIds);

        // 遍历这个标签的组成部分标签
        foreach ($combinedTags as $t) {
            // 递归查询这个标签，把查询到的页面 ID 合并到之前查询到的页面 ID 中
            $pageIds = array_merge($pageIds, $this->getPagesIdsByCombinedTag($t, $namespace, array_merge($searchedTags, array($tag))));
        }

        // 遍历这个标签的所有子标签，子标签无论组合标签如何设置都要显示，继承的权重高于组合的权重，因此子标签在组成标签后处理
        foreach ($subTags as $t) {
            // 递归查询这个标签，把查询到的页面 ID 合并到之前查询到的页面 ID 中
            $pageIds = array_merge($pageIds, $this->getPagesIdsByCombinedTag($t, $namespace, array_merge($searchedTags, array($tag))));
        }

        // 去重
        $pageIds = array_unique($pageIds);

        // 过滤掉没有浏览权限的页面
        $pageIds = array_filter($pageIds, 'auth_quickaclcheck');

        return $pageIds;
    }

    /**
     * 获取使用了指定标签组合出的标签
     * 
     * @param string $tag
     * @return array 使用这个标签进行组合的标签
     */
    public function getCombinedTagsByTag($tag)
    {
        // 读取标签组合信息，是所有标签的组合信息
        $combinationInfos = $this->loadCombinationInfo();

        // 准备一个数组存储关联的标签
        $combinedTags = array();

        // 遍历关联信息
        foreach ($combinationInfos as $mainTag => $combinationInfo) {
            // TODO：在这里判断出一个标签是否使用了传入的标签，不能用简单的字符串包含进行判断，需要把标签文本转为标签列表才行

            // 把组合信息转为单个标签字符串的数组，这样可以防止直接使用字符串匹配可能导致的匹配到部分重名的标签
            $subTags = array_filter(array_map('trim', explode(' ', preg_replace('/\s+/', ' ', $combinationInfo))));

            // 遍历拆分出的标签
            foreach ($subTags as $subTag) {
                // 如果拆分出的标签和传入的标签相同，说明现在遍历到的这个关联信息的主标签使用了传入的标签进行组合
                if (strcmp($tag, $subTag) === 0) {
                    // 把这个组合出来的主标签存入关联的标签数组
                    $combinedTags[] = $mainTag;
                }
            }
        }

        return $combinedTags;
    }

    /**
     * 根据标签命名空间获取其中的所有标签，如果命名空间和标签重名都会获取到
     * 
     * @param string $tag 标签命名空间名称或标签名称
     */
    public function getTagsByTagNamespace($tagNamespace)
    {
        // 从 Tag 插件那里获取所有的标签
        $allTags = array_keys(idx_get_indexer()->histogram(1, 0, 3, 'subject'));

        // 准备一个数组接收匹配的标签
        $tagsInNamespace = array();

        // 遍历所有标签
        foreach ($allTags as $tag) {
            // 如果标签名是以 “传入的名字:” 开头，说明是这个命名空间下的标签，存入
            // 如果标签名和传入的名字完全相同，说明就是这个标签，存入
            if (@preg_match('/^' . $tagNamespace . ':.*$/i', $tag) || $tagNamespace === $tag) {
                $tagsInNamespace[] = $tag;
            }
        }

        dbglog('筛选出的标签列表 = ');
        dbglog($matchedTags);

        return $tagsInNamespace;
    }
}
