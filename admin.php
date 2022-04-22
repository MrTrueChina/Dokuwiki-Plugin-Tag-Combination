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

use \dokuwiki\Extension\AdminPlugin;
use \dokuwiki\Form\Form;

global $INPUT;

// 当 form 发出 tagcombination_loadTagInfo 的 POST 时这段代码会被调用，tagcombination_loadTagInfo 是在后面写的独有字段
if ($INPUT->has('tagcombination_loadTagInfo')) {
    // 把请求里的输入框文本保存到全局变量里
    global $tagcombinationLoadTagText;
    $tagcombinationLoadTagText = $INPUT->str('tagInput');

    // dbglog('收到打开加载标签联系页面事件，记录输入的标签内容 = '.$tagcombinationLoadTagText);

    // 设为已经加载标签
    global $tagcombinationTagLoaded;
    $tagcombinationTagLoaded = true;
}

// 当 form 发出 tagcombination_editTagInfo 的 POST 时这段代码会被调用，tagcombination_editTagInfo 是在后面写的独有字段
if ($INPUT->has('tagcombination_editTagInfo')) {
    // 把请求携带的参数取出来
    $tag = $INPUT->str('tag');
    $combination = $INPUT->str('combinationInfoInput');

    // dbglog('收到打开编辑标签联系页面事件，记录输入的关联内容 = '.$tagcombinationCombinationInfoText);
    // dbglog('收到打开编辑标签联系页面事件，$INPUT = ');
    // dbglog($INPUT);

    // 编辑标签组合信息
    $helper = plugin_load('helper', 'tagcombination');
    $helper->editTagCombinationInfo($tag, $combination);

    // 设为未加载标签
    global $tagcombinationTagLoaded;
    $tagcombinationTagLoaded = false;
}

/**
 * admin 组件，这部分会修改管理页面的内容
 */
class admin_plugin_tagcombination extends AdminPlugin
{
    // 覆写的获取菜单栏中按钮标题的方法
    public function getMenuText($language)
    {
        return $this->getLang('combineTags');
    }

    // 获取按钮在菜单页面显示位置的方法，我这个插件只加一个按钮应该不用太在意吧
    function getMenuSort()
    {
        return 1500;
    }

    // 这个按钮是不是只有 admin 可以用
    function forAdminOnly()
    {
        return false;
    }

    // 这个按钮点击后渲染页面的方法
    function html()
    {
        // 根据是否加载了标签进行分别绘制
        global $tagcombinationTagLoaded;
        if (!$tagcombinationTagLoaded) {
            // 绘制加载标签组合信息的页面
            $this->drawLoadCombinationPage();
        } else {
            // 绘制编辑标签组合信息的页面
            $this->drawEditCombinationPage();
        }
    }

    /**
     * 绘制加载标签组合信息的页面
     */
    public function drawLoadCombinationPage()
    {
        // 绘制标题，使用国际化方法获取标题文本
        print '<h1>' . hsc($this->getLang('loadCombinationPageTitle')) . '</h1>' . DOKU_LF;

        // 创建 form 表单并设为使用 POST
        $form = new Form(array('method' => 'post'));

        // 标签输入框的标题
        $tagLabel = $form->addLabel($this->getLang('tagLabel'));
        $tagLabel->attr('class', 'tagcombination_form');

        // 标签输入框
        $tagInput = $form->addTextInput('tagInput');
        $tagInput->attr('class', 'tagcombination_form');
        $tagInput->attr('id', 'tagcombination_tag_input');

        // 加载按钮
        $form->addButton('tagcombination_loadTagInfo', $this->getLang('loadCombinationPageLoadButton'));
        $form->attr('class', 'tagcombination_form');

        // 绘制到页面上
        print $form->toHTML() . DOKU_LF;
    }

    /**
     * 绘制编辑标签组合信息的页面
     */
    public function drawEditCombinationPage()
    {
        // 加载保存在全局变量里的标签
        global $tagcombinationLoadTagText;

        // 绘制标题，使用国际化方法获取标题文本
        print '<h1>' . hsc($this->getLang('editCombinationPageTitle')) . ' | ' . $tagcombinationLoadTagText . '</h1>' . DOKU_LF;

        // 创建 form 表单并设为使用 POST
        $form = new Form(array('method' => 'post'));

        // 组合信息输入框的标题
        $combinationInfoLabel = $form->addLabel($this->getLang('combinationInfoLabel'));
        $combinationInfoLabel->attr('class', 'tagcombination_form');

        // 组合信息输入框
        $combinationInfoTextarea = $form->addTextArea('combinationInfoInput');
        $combinationInfoTextarea->attr('class', 'tagcombination_form');
        $combinationInfoTextarea->attr('id', 'tagcombination_combination_info_input');

        // 添加不在页面上显示但在提交时有效的元素，用于传递标签
        $form->setHiddenField('tag', $tagcombinationLoadTagText);

        // 确认编辑按钮
        $form->addButton('tagcombination_editTagInfo', $this->getLang('editCombinationPageEditButton'));
        $form->attr('class', 'tagcombination_form');
        
        // 给组合信息输入框存入初始值，就是之前编辑的关联信息
        $helper = $this->loadHelper('tagcombination');
        $combinationInfoTextarea->val($helper->loadTagCombinationInfo($tagcombinationLoadTagText));

        // dbglog('获取标签 ' . $tagcombinationLoadTagText . ' 的组合信息 = ' . $helper->loadTagCombinationInfo($tagcombinationLoadTagText));

        // 绘制到页面上
        print $form->toHTML() . DOKU_LF;
    }
}
