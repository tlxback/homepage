// 页面加载后显示公告
window.onload = function() {
    const modal = document.getElementById('announcement-modal');
    if (modal) {
        modal.classList.remove('hidden');
    }
};

function closeAnnouncement() {
    const modal = document.getElementById('announcement-modal');
    if (modal) modal.classList.add('hidden');
}
