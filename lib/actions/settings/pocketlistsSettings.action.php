<?php

class pocketlistsSettingsAction extends waViewAction
{
    public function execute()
    {
        $us = new pocketlistsUserSettings(wa()->getUser()->getId());
        $settings = $us->getAllSettings();
        $this->view->assign('settings', $settings);

        $inbox_list_id = $us->getStreamInboxList();
        if ($inbox_list_id) {
            $lm = new pocketlistsListModel();
            $inbox_list = $lm->getById($inbox_list_id);
            $this->view->assign('inbox_lists', $lm->getLists());
            $this->view->assign('inbox_list', $inbox_list);
        }

        $asp = new waAppSettingsModel();
        $this->view->assign(
            'last_recap_cron_time',
            $asp->get(wa()->getApp(), 'last_recap_cron_time')
        );

        $this->view->assign('cron_command', $this->getConfig()->getCronJob('recap_mail'));
        $this->view->assign('admin', pocketlistsRBAC::isAdmin());
    }
}
