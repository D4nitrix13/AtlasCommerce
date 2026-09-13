export interface Store {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
  email: string | null;
  phone: string | null;
  whatsapp: string | null;
  address: string | null;
  description: string | null;
  primaryColor: string | null;
  secondaryColor: string | null;
  maintenance: boolean;
  active: boolean;
}

export interface StoreDomain {
  id: number;
  storeId: number;
  domain: string;
  primary: boolean;
  active: boolean;
}

export interface StoreSocialLink {
  id: number;
  storeId: number;
  platform: string;
  url: string;
  active: boolean;
}
