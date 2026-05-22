import React from 'react';
import MaterialCommunityIcon from 'react-native-vector-icons/MaterialCommunityIcons';
import {Ionicons, MaterialIcons, MaterialCommunityIcons} from '@expo/vector-icons';
import FeatherIcon from 'react-native-vector-icons/Feather';
import FontAwesome from 'react-native-vector-icons/FontAwesome5';

const Icon = ({
  Feather,
  size = 24,
  Ion,
  color = '#566476',
  Material,
  MaterialIcon,
  Symbols,
  FontAwesome,
  ...props
}) => {

  // Material Symbols - menggunakan Google Material Symbols (versi terbaru)
  // FILL 0 = outline style (default untuk Material Symbols)
  // Menggunakan MaterialCommunityIcons dengan varian outline untuk mendapatkan efek FILL 0
  if (Symbols) {
    // Mapping icon Material Symbols ke varian outline MaterialCommunityIcons
    // MaterialCommunityIcons menggunakan nama yang berbeda untuk beberapa icon
    // Hanya gunakan mapping yang sudah didefinisikan, jangan otomatis tambahkan "-outline"
    const iconMapping = {
      'home': 'home-outline',
      'star': 'star-outline',
      'settings': 'cog-outline',
      'user': 'account-outline',
      'person': 'account-outline',
      'search': 'magnify',
      'menu': 'menu',
      'close': 'close',
      'add': 'plus',
      'delete': 'delete-outline',
      'edit': 'pencil-outline',
      'save': 'content-save-outline',
      'share': 'share-outline',
      'bookmark': 'bookmark-outline',
      'favorite': 'heart-outline',
      'notifications': 'bell-outline',
      'mail': 'email-outline',
      'phone': 'phone-outline',
      'location': 'map-marker-outline',
      'calendar': 'calendar-outline',
      'folder': 'folder-outline',
      'image': 'image-outline',
      'file': 'file-outline',
    };
    
    // Gunakan mapping jika ada, jika tidak gunakan nama asli
    // MaterialCommunityIcons akan menampilkan icon jika tersedia, atau placeholder jika tidak
    const iconName = iconMapping[Symbols] || Symbols;
    
    return (
      <MaterialCommunityIcons 
        name={iconName} 
        size={size} 
        color={color} 
        {...props} 
      />
    );
  }

  if (FontAwesome) {
      // Pastikan FontAwesome adalah string dan valid
      const iconName = typeof FontAwesome === 'string' ? FontAwesome : String(FontAwesome);
      // Gunakan solid={true} untuk FontAwesome5 file icons
      const useSolid = props.solid !== undefined ? props.solid : true;
      return(<FontAwesome name={iconName} size={size} color={color} solid={useSolid} {...props} />)
  } else if (Feather) {
      return(<FeatherIcon name={Feather} size={size} color={color} {...props} />)
  } else if (Material) {
      // Transform invalid Material icon names to valid MaterialCommunityIcons
      let iconName = Material;
      if (iconName === 'business') iconName = 'office-building';
      if (iconName === 'visibility_off') iconName = 'eye-off';
      if (iconName === 'visibility') iconName = 'eye';
      if (iconName === 'visibility_outlined') iconName = 'eye-outline';
      if (iconName === 'visibility_off_outlined') iconName = 'eye-off-outline';
      
      return(<MaterialCommunityIcon name={iconName} size={size} color={color} {...props}/>)
  } else if (MaterialIcon) {
      return(<MaterialIcons name={MaterialIcon} size={size} color={color} {...props} />)
  } else if (Ion) {
      return ( <Ionicons name={Ion} color={color} size={size} {...props} />)
  }
  
  return null;
}

// Wrapper component untuk Material Symbols dengan prop 'name' (untuk kompatibilitas)
export const SymbolsIcon = ({ name, size, color, ...props }) => {
  return <Icon Symbols={name} size={size} color={color} {...props} />;
};

export default Icon;
