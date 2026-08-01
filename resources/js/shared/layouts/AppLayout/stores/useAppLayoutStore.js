import { ref } from 'vue';

export function useAppLayoutStore() {
    const isSidebarCollapsed = ref(false);
    const isSidebarOpen = ref(false);
    const isLoading = ref(false);

    const expandSidebar = () => {
        isSidebarCollapsed.value = false;
    };

    const collapseSidebar = () => {
        isSidebarCollapsed.value = true;
    };

    const toggleSidebar = () => {
        isSidebarCollapsed.value = !isSidebarCollapsed.value;
    };

    const closeMobileSidebar = () => {
        isSidebarOpen.value = false;
    };

    const openMobileSidebar = () => {
        isSidebarOpen.value = true;
    };

    const toggleMobileSidebar = () => {
        isSidebarOpen.value = !isSidebarOpen.value;
    };

    const setLoading = (value) => {
        isLoading.value = value;
    };

    return {
        collapseSidebar,
        closeMobileSidebar,
        expandSidebar,
        isLoading,
        isSidebarCollapsed,
        isSidebarOpen,
        openMobileSidebar,
        setLoading,
        toggleMobileSidebar,
        toggleSidebar,
    };
}
