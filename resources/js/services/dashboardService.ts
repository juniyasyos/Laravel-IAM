import api from './api';
import type { Application } from '../types';

export interface ApplicationResponse {
    app_key: string;
    name: string;
    description?: string;
    app_url?: string;
    logo_url?: string;
    enabled: boolean;
    roles?: string[];
    redirect_uris?: string[];
    status: 'active' | 'inactive';
    urls?: {
        primary?: string;
        all_redirects?: string[];
    };
}

export interface ApplicationsApiResponse {
    applications: ApplicationResponse[];
    total_accessible_apps: number;
    timestamp: string;
}

export const dashboardService = {
    async getApplications(): Promise<ApplicationResponse[]> {
        const response = await api.get<ApplicationsApiResponse>('/api/users/applications');
        return response.data.applications;
    },
};